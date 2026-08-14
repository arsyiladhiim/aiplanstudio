<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleCsrfCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('sanctum/csrf-cookie') && RateLimiter::tooManyAttempts('sanctum-csrf:'.($request->ip() ?? 'unknown'), 15)) {
            return response()->json(['message' => 'Terlalu banyak permintaan. Coba lagi nanti.'], 429);
        }

        RateLimiter::hit('sanctum-csrf:'.($request->ip() ?? 'unknown'), 1);

        return $next($request);
    }
}
