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
        // 2. Identify by subdomain (SaaS automatic)
        if (!$tenantId) {
            $host = $request->getHost();
            // Assuming your domain is 'soymetrix.com' or similar
            // If it's a subdomain like 'cliente1.soymetrix.com'
            $parts = explode('.', $host);
            if (count($parts) > 2) {
                $subdomain = $parts[0];
                if ($subdomain !== 'www' && $subdomain !== 'eventos2' && $subdomain !== 'api') {
                    $tenant = \App\Models\Tenant::where('slug', $subdomain)->first();
                    if ($tenant) {
                        $tenantId = $tenant->id;
                    }
                }
            }
            
            // Default for the main production domain
            if (!$tenantId && ($host === 'eventos2.soymetrix.com' || $host === 'localhost')) {
                $tenant = \App\Models\Tenant::where('slug', 'metrix-enterprise')->first() ?: \App\Models\Tenant::first();
                if ($tenant) {
                    $tenantId = $tenant->id;
                }
            }
        }

        // 3. Fallback to header
        if (!$tenantId && $request->hasHeader('X-Tenant-Id')) {
            $tenantId = $request->header('X-Tenant-Id');
        }
        
        // 4. Fallback to Domain/Slug header
        if (!$tenantId && $request->hasHeader('X-Tenant-Domain')) {
            $tenant = \App\Models\Tenant::where('slug', $request->header('X-Tenant-Domain'))->first();
            if ($tenant) $tenantId = $tenant->id;
        }

        if ($tenantId) {
            app()->instance('tenant_id', $tenantId);
        }

        return $next($request);
    }
}
