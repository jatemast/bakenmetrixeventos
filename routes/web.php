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
