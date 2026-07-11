<?php

namespace App\Http\Middleware;

use App\Models\SyncDevice;
use App\Support\CuentaContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->bearerToken($request);

        if (! $token) {
            return response()->json([
                'success' => false,
                'errors' => ['Sync token not provided'],
            ], 401);
        }

        $device = SyncDevice::query()
            ->where('token_hash', SyncDevice::hashToken($token))
            ->whereNull('revoked_at')
            ->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'errors' => ['Invalid or revoked sync token'],
            ], 401);
        }

        $device->touchLastSeen();

        CuentaContext::set((int) $device->cuenta_id);
        $request->attributes->set('cuenta_id', (int) $device->cuenta_id);
        $request->attributes->set('sync_device', $device);

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
