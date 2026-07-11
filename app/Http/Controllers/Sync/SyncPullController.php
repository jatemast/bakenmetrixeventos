<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\SyncPullService;
use App\Services\Sync\SyncPushService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncPullController extends Controller
{
    public function __construct(
        private readonly SyncPullService $pullService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $tipo = (string) $request->query('tipo', '');
        $tenantId = $request->query('tenant_id');
        $cuentaId = (int) $request->attributes->get('cuenta_id');

        if ($tenantId !== null && (int) $tenantId !== $cuentaId) {
            return response()->json([
                'success' => false,
                'errors' => ['tenant_id does not match sync device account'],
            ], 403);
        }

        $since = filled($request->query('since'))
            ? Carbon::parse((string) $request->query('since'))
            : null;

        $eventoId = filled($request->query('evento_id'))
            ? (int) $request->query('evento_id')
            : null;

        $result = $this->pullService->pull($tipo, $cuentaId, $since, $eventoId);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'errors' => $result['errors'] ?? ['Pull failed'],
            ], $result['status'] ?? 400);
        }

        return response()->json($result);
    }
}
