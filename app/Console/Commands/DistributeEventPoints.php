<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\BonusPointsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to distribute points to event attendees and leaders
 * 
 * **FALLBACK/MANUAL TOOL** - Normal flow uses queue jobs (ProcessEventPointsDistribution)
 * 
 * This command serves as:
 * - Manual trigger for specific events
 * - Fallback for missed queue jobs  
 * - Safety net to catch events that weren't auto-processed
 * 
 * Process:
 * 1. Find events that ended and haven't distributed points
 * 2. Award base points to all attendees (based on bonus_points_for_attendee)
 * 3. Award leader bonuses (based on bonus_points_for_leader * guests_attended)
 * 4. Mark event as points_distributed = true
 * 
 * @see \App\Jobs\ProcessEventPointsDistribution - Automatic queue-based processing
 */
class DistributeEventPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:distribute-points 
                            {--dry-run : Run without making changes}
                            {--event= : Process specific event ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Distribute loyalty points to event attendees and leader bonuses';

    /**
     * Bonus points service
     *
     * @var BonusPointsService
     */
    protected $bonusService;

    /**
     * Create a new command instance.
     */
    public function __construct(BonusPointsService $bonusService)
    {
        parent::__construct();
        $this->bonusService = $bonusService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificEventId = $this->option('event');

        $this->info('Starting event points distribution...');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Build query for events that need points distribution
        $query = Event::query()
            ->where('points_distributed', false)
            ->whereNotNull('ended_at')
            ->where('ended_at', '<=', now()->subHour()); // 1 hour grace period

        if ($specificEventId) {
            $query->where('id', $specificEventId);
        }

        $events = $query->with(['attendees' => function ($query) {
            $query->where('attendance_status', 'completed');
        }])->get();

        if ($events->isEmpty()) {
            $this->info('No events found that need points distribution');
            return Command::SUCCESS;
        }

        $this->info("Found {$events->count()} event(s) to process\n");

        $totalStats = [
            'events_processed' => 0,
            'total_attendees' => 0,
            'total_leaders' => 0,
            'total_points' => 0,
            'failed_events' => 0,
        ];

        foreach ($events as $event) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Event: {$event->detail}");
            $this->line("ID: {$event->id}");
            $this->line("Ended: {$event->ended_at}");
            $this->line("Attendees: {$event->attendees->count()}");
            
            if ($isDryRun) {
                $this->warn("[DRY RUN] Would distribute points for this event");
                $totalStats['events_processed']++;
                $totalStats['total_attendees'] += $event->attendees->count();
                continue;
            }

            // Distribute points using BonusPointsService
            $result = $this->bonusService->distributeEventBonuses($event);

            if ($result['success']) {
                $this->info("Success!");
                $this->line("Attendee Points: {$result['attendee_points_distributed']}");
                $this->line("Leader Bonuses: {$result['leader_bonus_points_distributed']}");
                $this->line("Total Points: {$result['total_points_distributed']}");
                
                $totalStats['events_processed']++;
                $totalStats['total_points'] += $result['total_points_distributed'];
                
                // Log leader details
                if (!empty($result['details'])) {
                    $totalStats['total_leaders'] += count($result['details']);
                    foreach ($result['details'] as $detail) {
                        $this->line("      👤 Leader #{$detail['leader_id']}: +{$detail['bonus_points']} pts ({$detail['guests_attended']} guests)");
                    }
                }
            } else {
                $this->error("Failed: {$result['message']}");
                $totalStats['failed_events']++;
                
                Log::error("DistributeEventPoints failed for event {$event->id}", [
                    'event_id' => $event->id,
                    'error' => $result['message'],
                ]);
            }
        }

        // Summary
        $this->line("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info('SUMMARY');
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Events Processed: {$totalStats['events_processed']}");
        $this->line("Leaders Rewarded: {$totalStats['total_leaders']}");
        $this->line("Total Points: {$totalStats['total_points']}");
        
        if ($totalStats['failed_events'] > 0) {
            $this->error("Failed Events: {$totalStats['failed_events']}");
        }

        Log::info('DistributeEventPoints completed', $totalStats);

        return Command::SUCCESS;
    }
}
