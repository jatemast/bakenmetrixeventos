<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CentralAuthService;
use App\Support\CuentaContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates Metrix Central JWT on every protected Hub API request.
 */
class HubAuthenticate
{
    public function __construct(
        private readonly CentralAuthService $centralAuth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->bearerToken($request);
        if (! $token) {
            return $this->unauthorized('Token not provided');
        }

        if (config('central.api_url') === '') {
            return $this->unauthorized('Authentication is not configured');
        }

        try {
            $cacheKey = 'hub:central_auth:'.hash('xxh128', $token);
            $centralUser = Cache::remember($cacheKey, 300, function () use ($token) {
                $user = $this->centralAuth->me($token);
                $this->centralAuth->assertEventsModuleAccess($user);

                return $user;
            });

            $accountId = isset($centralUser['account_id'])
                ? (int) $centralUser['account_id']
                : null;

            $localUser = User::query()->firstOrNew(['email' => $centralUser['email']]);
            $localUser->fill([
                'name' => $centralUser['name'] ?? $centralUser['full_name'] ?? $centralUser['email'],
                'role' => 'admin',
                'is_active' => true,
                'cuenta_id' => $accountId,
            ]);

            if (! $localUser->exists) {
                $localUser->password = Hash::make(Str::random(48));
            }

            $localUser->save();

            Auth::guard('sanctum')->setUser($localUser);
            $request->setUserResolver(static fn () => $localUser);
            $request->attributes->set('central_user', $centralUser);
            $request->attributes->set('central_token', $token);
            $this->applyTenantContext($request, $accountId);

            return $next($request);
        } catch (RuntimeException $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 401;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 401);
    }

    private function applyTenantContext(Request $request, ?int $cuentaId): void
    {
        CuentaContext::set($cuentaId);
        $request->attributes->set('cuenta_id', $cuentaId);
    }
}
