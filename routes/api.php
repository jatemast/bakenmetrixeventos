<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\EventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::get('/campaigns/{id}', [CampaignController::class, 'show']);

    // Rutas para eventos
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/campaigns/{campaignId}/events', [EventController::class, 'index']);
});