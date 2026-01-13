<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\BonusPointsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Queue job to distribute points exactly when event grace period expires
 * 
 * Triggered automatically when event ends based on event's end time + grace period.
 * This replaces the hourly polling approach with precise, event-based scheduling.
 * 
 * @see BonusPointsService::distributeEventBonuses()
 */
class ProcessEventPointsDistribution implements ShouldQueue
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
    public function handle(BonusPointsService $bonusService): void
    {
        Log::info("ProcessEventPointsDistribution started for event {$this->event->id}");

        // Double-check event is actually ended and hasn't been processed
        if (!$this->event->ended_at) {
            Log::warning("Event {$this->event->id} is not ended yet, skipping points distribution");
            return;
        }

        if ($this->event->points_distributed) {
            Log::info("Event {$this->event->id} already has points distributed, skipping");
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

        // Distribute points
        $result = $bonusService->distributeEventBonuses($this->event);

        if ($result['success']) {
            Log::info("Successfully distributed points for event {$this->event->id}", [
                'total_points' => $result['total_points_distributed'],
                'attendee_points' => $result['attendee_points_distributed'],
                'leader_bonus_points' => $result['leader_bonus_points_distributed'],
                'leaders_count' => $result['leaders_processed'],
            ]);

            // Update scheduled flag
            $this->event->update(['points_distribution_scheduled' => false]);
        } else {
            Log::error("Failed to distribute points for event {$this->event->id}: {$result['message']}");
            throw new \Exception("Points distribution failed: {$result['message']}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessEventPointsDistribution failed for event {$this->event->id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Reset scheduled flag so it can be retried manually
        $this->event->update(['points_distribution_scheduled' => false]);
    }
}

