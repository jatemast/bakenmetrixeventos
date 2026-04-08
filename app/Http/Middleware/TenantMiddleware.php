<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = null;

        // 1. Prioritize authenticated user context
        if (auth()->check()) {
            $tenantId = auth()->user()->tenant_id;
        } 
        // 2. Fallback to header (useful for pre-login or multi-tenant choice)
        elseif ($request->hasHeader('X-Tenant-Id')) {
            $tenantId = $request->header('X-Tenant-Id');
        }

        if ($tenantId) {
            app()->instance('tenant_id', $tenantId);
        }

        return $next($request);
    }
}
