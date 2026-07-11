<?php

use App\Http\Controllers\Sync\SyncHealthController;
use App\Http\Controllers\Sync\SyncPullController;
use App\Http\Controllers\Sync\SyncPushController;
use Illuminate\Support\Facades\Route;

Route::get('/health', SyncHealthController::class);

Route::middleware('sync.auth')->group(function () {
    Route::get('/pull', SyncPullController::class);
    Route::post('/sync', SyncPushController::class);
});
