<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':60,1',
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\TenantMiddleware::class,
            \App\Http\Middleware\ApiLoggingMiddleware::class,
        ]);

        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\TenantMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/public/register',
            'api/public/store-super-persona',
            'api/public/*'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
