<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\SyncDevice;
use App\Services\Sync\SyncPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncPushController extends Controller
{
    public function __construct(
        private readonly SyncPushService $pushService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string'],
            'payload' => ['required', 'array'],
            'batch_id' => ['nullable', 'string', 'max:191'],
        ]);

        /** @var SyncDevice|null $device */
        $device = $request->attributes->get('sync_device');
        $cuentaId = (int) $request->attributes->get('cuenta_id');

        $result = $this->pushService->push(
            $validated['tipo'],
            $validated['payload'],
            $cuentaId,
            $device,
            $validated['batch_id'] ?? null,
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'errors' => $result['errors'] ?? ['Push failed'],
            ], $result['status'] ?? 422);
        }

        return response()->json($result);
    }
}
