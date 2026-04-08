<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:create', function () {
    $tenant = \App\Models\Tenant::firstOrCreate(['id' => 1], [
        'name' => 'Metrix Principal',
        'slug' => 'metrix-principal'
    ]);

    $user = \App\Models\User::firstOrNew(['email' => 'edwinabello0422@gmail.com']);
    $user->name = 'Edwin Master';
    $user->password = \Illuminate\Support\Facades\Hash::make('1234');
    $user->tenant_id = $tenant->id;
    $user->role = 'admin'; // also set role
    $user->save();
    $this->info('User created or updated successfully');
});

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
