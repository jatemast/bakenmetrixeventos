<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\SyncDevice;
use App\Support\CuentaContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cuentaId = CuentaContext::require();

        $devices = SyncDevice::query()
            ->where('cuenta_id', $cuentaId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SyncDevice $device) => [
                'id' => $device->id,
                'label' => $device->label,
                'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
                'revoked_at' => optional($device->revoked_at)?->toIso8601String(),
                'created_at' => optional($device->created_at)?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ]);

        $cuentaId = CuentaContext::require();
        [$device, $plainToken] = SyncDevice::issue($cuentaId, $validated['label']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $device->id,
                'label' => $device->label,
                'cuenta_id' => $cuentaId,
                'token' => $plainToken,
            ],
            'meta' => [
                'message' => 'Store this token securely. It will not be shown again.',
            ],
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $cuentaId = CuentaContext::require();

        $device = SyncDevice::query()
            ->where('cuenta_id', $cuentaId)
            ->whereKey($id)
            ->firstOrFail();

        $device->revoke();

        return response()->json([
            'success' => true,
            'data' => ['id' => $device->id, 'revoked_at' => $device->revoked_at?->toIso8601String()],
        ]);
    }
}
