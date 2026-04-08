<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name', 'Laravel'),
        'version' => app()->version(),
        'status' => 'active',
        'type' => 'API',
        'message' => 'Event Management API is running. All endpoints are available at for testing'
    ]);
});

Route::get('/censo-oficial', function (Illuminate\Http\Request $request) {
    return view('censo_form', ['leader_id' => $request->query('leader_id')]);
})->name('censo.oficial');

Route::get('/admin/dashboard', [\App\Http\Controllers\AdminDashboardController::class, 'index'])->name('admin.dashboard');
Route::get('/api/admin/map-data', [\App\Http\Controllers\AdminDashboardController::class, 'getMapData'])->name('admin.map-data');

Route::get('/mi-carnet/{code}', function ($code) {
    $persona = \App\Models\Persona::where('codigo_ciudadano', $code)->firstOrFail();
    return view('mi_carnet', compact('persona'));
})->name('citizen.carnet');

Route::get('/puntos-mi-cuenta', function (Illuminate\Http\Request $request) {
    $whatsapp = preg_replace('/[^0-9]/', '', $request->query('whatsapp'));
    $persona = \App\Models\Persona::where('numero_celular', 'LIKE', '%' . $whatsapp . '%')->first();

    if (!$persona) {
        return "<h3>No se encontró tu registro. Por favor regístrate primero en WhatsApp.</h3>";
    }

    $history = \App\Models\BonusPointHistory::where('persona_id', $persona->id)
        ->orderBy('created_at', 'desc')
        ->get();

    return view('loyalty_score', [
        'persona' => $persona,
        'history' => $history
    ]);
})->name('loyalty.score');

Route::get('/censo-registro', function (Illuminate\Http\Request $request) {
    return view('pwa_registration', ['whatsapp' => $request->query('whatsapp')]);
})->name('pwa.registration');

