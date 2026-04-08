<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Handle Preflight (OPTIONS)
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        $origin = $request->headers->get('Origin');

        // 2. Define allowed origins (Local, Production, and Ngrok for development)
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:3000',
            'https://soymetrix.com',
            'https://bakenmetrix.com',
        ];

        // Allowed patterns (like *.ngrok-free.dev or *.soymetrix.com)
        $allowedPatterns = [
            '/^https:\/\/[a-z0-9-]+\.ngrok-free\.dev$/',
            '/^https:\/\/[a-z0-9-]+\.soymetrix\.com$/',
        ];

        $isAllowed = false;

        if ($origin) {
            if (in_array($origin, $allowedOrigins)) {
                $isAllowed = true;
            } else {
                foreach ($allowedPatterns as $pattern) {
                    if (preg_match($pattern, $origin)) {
                        $isAllowed = true;
                        break;
                    }
                }
            }
        }

        // 3. Set Headers based on validation
        if ($isAllowed) {
            // IMPORTANT: If Access-Control-Allow-Credentials is true, 
            // Origin cannot be '*' and must match exactly.
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            // Fail-safe: Only allow origin if explicitly matched, otherwise return nothing or restricted
            // For general public APIs you could use '*', but never with Credentials: true.
            $response->headers->set('Access-Control-Allow-Origin', config('app.url'));
        }

        // 4. Common CORS Headers
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, X-Tenant-Id, X-Application-Id, Application, ngrok-skip-browser-warning');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours

        return $response;
    }
}