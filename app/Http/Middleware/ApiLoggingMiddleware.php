<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLoggingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $startTime;

        Log::channel('daily')->info('API_TRACKING', [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'duration' => round($duration * 1000, 2) . 'ms',
            'status' => $response->getStatusCode(),
            'user' => $request->user()?->email ?? 'Guest',
            'payload' => $request->except(['password', 'password_confirmation']),
        ]);

        return $response;
    }
}
