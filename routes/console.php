<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * - Schedule recurring tasks for event management automation
 * - Auto-close events after grace period (1 hour after event ends)
 * - Distribute points to attendees and leaders
 */
Schedule::command('events:auto-close')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        info('Auto-close events completed successfully');
    })
    ->onFailure(function () {
        error('Auto-close events failed');
    });

Schedule::command('events:distribute-points')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->after(function () {
        info('Points distribution completed');
    })
    ->onFailure(function () {
        error('Points distribution failed');
    });
