<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\MascotaController;
use App\Http\Controllers\AttendanceController; // Importar el nuevo controlador
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Ruta pública para ver un evento por su código de check-in
Route::get('/events/public/{checkinCode}', [EventController::class, 'showPublic']);

// Rutas de asistencia (check-in/check-out)
Route::post('/events/checkin', [AttendanceController::class, 'checkin']);
Route::post('/events/checkout', [AttendanceController::class, 'checkout']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::get('/campaigns/{id}', [CampaignController::class, 'show']);
    Route::put('/campaigns/{id}', [CampaignController::class, 'update']);
    Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy']);

    // Rutas para eventos
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/campaigns/{campaignId}/events', [EventController::class, 'index']);
    Route::put('/events/{id}', [EventController::class, 'update']); // Ruta para actualizar eventos

    // Rutas para Mascotas anidadas bajo Personas
    Route::apiResource('personas.mascotas', MascotaController::class);
});

// Rutas para Personas (públicas)
Route::apiResource('personas', PersonaController::class);