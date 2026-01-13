<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Queue job to auto-checkout attendees after grace period
 * 
 * Triggered automatically when event ends. Auto-checks out attendees who
 * forgot to checkout, marking them as attended so they receive points.
 * 
 * Runs BEFORE ProcessEventPointsDistribution to ensure attendees are
 * marked as attended before points are calculated.
 */
class AutoCheckoutEventAttendees implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The event to process
     *
     * @var Event
     */
    protected $event;

    /**
     * Create a new job instance.
     */
    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("AutoCheckoutEventAttendees started for event {$this->event->id}");

        // Verify event is ended
        if (!$this->event->ended_at) {
            Log::warning("Event {$this->event->id} is not ended yet, skipping auto-checkout");
            return;
        }

        // Check if grace period has actually expired
        $gracePeriodEnd = $this->event->ended_at->copy()->addHours($this->event->grace_period_hours ?? 1);
        if (now()->lt($gracePeriodEnd)) {
            $minutesRemaining = now()->diffInMinutes($gracePeriodEnd);
            Log::warning("Event {$this->event->id} grace period not expired yet ({$minutesRemaining} minutes remaining), re-queueing");
            
            // Re-dispatch with delay
            $this->release($minutesRemaining * 60);
            return;
        }

        // Find attendees who checked in but haven't checked out
        $attendees = EventAttendee::where('event_id', $this->event->id)
            ->where('attendance_status', 'entered')
            ->whereNull('checkout_at')
            ->get();

        if ($attendees->isEmpty()) {
            Log::info("No attendees need auto-checkout for event {$this->event->id}");
            $this->event->update(['auto_close_scheduled' => false]);
            return;
        }

        DB::beginTransaction();
        
        try {
            $autoCheckoutTime = $gracePeriodEnd;
            $successCount = 0;
            $failCount = 0;

            foreach ($attendees as $attendee) {
                try {
                    $attendee->update([
                        'checkout_at' => $autoCheckoutTime,
                        'attendance_status' => 'completed',  // Changed to 'completed' to match BonusPointsService query
                        'notes' => ($attendee->notes ? $attendee->notes . ' | ' : '') . 
                                   'Auto-checked out after grace period',
                    ]);

                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    Log::error("Failed to auto-checkout attendee {$attendee->id}: {$e->getMessage()}");
                }
            }

            DB::commit();

            Log::info("Auto-checkout completed for event {$this->event->id}", [
                'total_attendees' => $attendees->count(),
                'success' => $successCount,
                'failed' => $failCount,
                'auto_checkout_time' => $autoCheckoutTime,
            ]);

            // Update scheduled flag
            $this->event->update(['auto_close_scheduled' => false]);

            // Now dispatch points distribution job (runs immediately after auto-checkout)
            ProcessEventPointsDistribution::dispatch($this->event);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Auto-checkout transaction failed for event {$this->event->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("AutoCheckoutEventAttendees failed for event {$this->event->id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Reset scheduled flag so it can be retried manually
        $this->event->update(['auto_close_scheduled' => false]);
    }
}

