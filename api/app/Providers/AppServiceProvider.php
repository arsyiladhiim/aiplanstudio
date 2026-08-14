<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('sanctum-csrf', function (Request $request) {
            return Limit::perSecond(5)->by($request->ip())->response(function () {
                return response()->json(['message' => 'Terlalu banyak permintaan. Coba lagi nanti.'], 429);
            });
        });
    }
}
