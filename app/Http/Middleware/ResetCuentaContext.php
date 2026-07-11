<?php

namespace App\Http\Middleware;

use App\Support\CuentaContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResetCuentaContext
{
    public function handle(Request $request, Closure $next): Response
    {
        CuentaContext::clear();

        return $next($request);
    }
}
