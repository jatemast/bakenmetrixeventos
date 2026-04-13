<?php

namespace App\Console\Commands;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateEventStatuses extends Command
{
    protected $signature = 'events:update-status';
    protected $description = 'Automatically update event statuses based on date/time (scheduled → active → completed)';

    public function handle(): int
    {
        $now = Carbon::now();
        $updated = ['to_active' => 0, 'to_completed' => 0];

        // 1. scheduled → active (event date+time has started)
        $toActivate = Event::where('status', 'scheduled')
            ->whereNotNull('date')
            ->whereNotNull('time')
            ->get()
            ->filter(function ($event) use ($now) {
                $start = Carbon::parse($event->date . ' ' . $event->time);
                return $now->gte($start);
            });

        foreach ($toActivate as $event) {
            $event->update(['status' => 'active']);
            $updated['to_active']++;
        }

        // 2. active → completed (event date+time+duration has passed)
        $toComplete = Event::where('status', 'active')
            ->get()
            ->filter(fn ($event) => $event->shouldBeEnded());

        foreach ($toComplete as $event) {
            $event->update(['status' => 'completed']);
            $event->schedulePostEventProcessing();
            $updated['to_completed']++;
        }

        // 3. Auto-update campaign statuses based on their events and dates
        $this->updateCampaignStatuses($now);

        $this->info("Updated: {$updated['to_active']} → active, {$updated['to_completed']} → completed");
        Log::info('Event status auto-update', $updated);

        return 0;
    }

    private function updateCampaignStatuses(Carbon $now): void
    {
        $campaigns = \App\Models\Campaign::whereNotIn('status', ['cancelled'])->get();

        foreach ($campaigns as $campaign) {
            $newStatus = null;

            if ($campaign->end_date && $now->gt(Carbon::parse($campaign->end_date)->endOfDay())) {
                $newStatus = 'completed';
            } elseif ($campaign->start_date && $now->gte(Carbon::parse($campaign->start_date)->startOfDay()) 
                && ($campaign->end_date && $now->lte(Carbon::parse($campaign->end_date)->endOfDay()))) {
                $newStatus = 'active';
            } elseif ($campaign->start_date && $now->lt(Carbon::parse($campaign->start_date)->startOfDay())) {
                $newStatus = 'scheduled';
            }

            if ($newStatus && $newStatus !== $campaign->status) {
                $campaign->update(['status' => $newStatus]);
            }
        }
    }
}
