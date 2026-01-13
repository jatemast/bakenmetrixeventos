<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Command to automatically close events with grace period checkout
 * 
 * **FALLBACK/MANUAL TOOL** - Normal flow uses queue jobs (AutoCheckoutEventAttendees)
 * 
 * This command serves as:
 * - Manual trigger for specific events
 * - Fallback for missed queue jobs
 * - Safety net to catch events that weren't auto-processed
 * 
 * Process:
 * 1. Find events ended 1+ hours ago with unchecked-out attendees
 * 2. Mark checked_in attendees as 'completed' (full_grace)
 * 3. Set checked_out_at = event end time + 1 hour
 * 4. This triggers points distribution (via DistributeEventPoints command)
 * 
 * @see \App\Jobs\AutoCheckoutEventAttendees - Automatic queue-based processing
 */
class AutoCloseEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:auto-close 
                            {--dry-run : Run without making changes}
                            {--event= : Process specific event ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-checkout attendees after grace period (1 hour after event ends)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificEventId = $this->option('event');

        $this->info('Starting auto-close events with grace period...');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find events that ended 1+ hours ago
        $gracePeriodCutoff = now()->subHour();
        
        $query = Event::query()
            ->whereNotNull('ended_at')
            ->where('ended_at', '<=', $gracePeriodCutoff);

        if ($specificEventId) {
            $query->where('id', $specificEventId);
        }

        // Only get events that have attendees needing auto-checkout
        $events = $query->whereHas('attendees', function ($q) {
            $q->where('status', 'checked_in')
              ->whereNull('checked_out_at');
        })->with(['attendees' => function ($q) {
            $q->where('status', 'checked_in')
              ->whereNull('checked_out_at');
        }])->get();

        if ($events->isEmpty()) {
            $this->info('No events found with attendees needing auto-checkout');
            return Command::SUCCESS;
        }

        $this->info("Found {$events->count()} event(s) with attendees to auto-checkout\n");

        $totalStats = [
            'events_processed' => 0,
            'attendees_closed' => 0,
            'failed_attendees' => 0,
        ];

        foreach ($events as $event) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Event: {$event->detail}");
            $this->line("ID: {$event->id}");
            $this->line("Ended: {$event->ended_at}");
            $this->line("Grace Period Expired: " . $event->ended_at->addHour());
            $this->line("Attendees Needing Checkout: {$event->attendees->count()}");

            if ($isDryRun) {
                $this->warn("[DRY RUN] Would auto-checkout {$event->attendees->count()} attendee(s)");
                $totalStats['events_processed']++;
                $totalStats['attendees_closed'] += $event->attendees->count();
                continue;
            }

            DB::beginTransaction();
            
            try {
                $eventStats = $this->processEventAttendees($event);
                
                $totalStats['events_processed']++;
                $totalStats['attendees_closed'] += $eventStats['closed'];
                $totalStats['failed_attendees'] += $eventStats['failed'];

                DB::commit();
                
                $this->info("Auto-checked out {$eventStats['closed']} attendee(s)");
                
                if ($eventStats['failed'] > 0) {
                    $this->warn("{$eventStats['failed']} attendee(s) failed");
                }
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                $this->error("Failed to process event: {$e->getMessage()}");
                
                Log::error("AutoCloseEvents failed for event {$event->id}", [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Summary
        $this->line("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info('SUMMARY');
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Events Processed: {$totalStats['events_processed']}");
        $this->line("Attendees Auto-Checked Out: {$totalStats['attendees_closed']}");
        
        if ($totalStats['failed_attendees'] > 0) {
            $this->error("Failed Attendees: {$totalStats['failed_attendees']}");
        }

        Log::info('AutoCloseEvents completed', $totalStats);

        return Command::SUCCESS;
    }

    /**
     * Process attendees for a specific event
     * 
     * @param Event $event
     * @return array
     */
    private function processEventAttendees(Event $event): array
    {
        $stats = [
            'closed' => 0,
            'failed' => 0,
        ];

        $autoCheckoutTime = $event->ended_at->copy()->addHour();

        foreach ($event->attendees as $attendee) {
            try {
                // Auto-checkout with grace period status
                $attendee->update([
                    'status' => 'checked_out',
                    'checked_out_at' => $autoCheckoutTime,
                    'attendance_status' => 'completed', // Mark as completed for points
                    'notes' => ($attendee->notes ? $attendee->notes . ' | ' : '') . 
                               'Auto-checked out after grace period',
                ]);

                $stats['closed']++;
                
                $this->line("      ✓ Auto-checked out persona #{$attendee->persona_id}");
                
            } catch (\Exception $e) {
                $stats['failed']++;
                
                $this->error("      ✗ Failed persona #{$attendee->persona_id}: {$e->getMessage()}");
                
                Log::error("Failed to auto-checkout attendee", [
                    'attendee_id' => $attendee->id,
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }
}
