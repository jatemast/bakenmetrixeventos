<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SyncHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'ok',
                'version' => '1',
            ],
            'meta' => [
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
