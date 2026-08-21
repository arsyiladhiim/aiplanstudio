<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Version;
use App\Policies\ProjectPolicy;
use App\Policies\VersionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        // CP-16.M2: per-user rate limit (keyed by email + IP fallback).
        // Prevents bot on single IP from locking out legit users via shared Cloudflare egress.
        $perUserEmail = function (Request $request, string $field): string {
            $email = strtolower((string) $request->input($field, ''));
            return $email !== '' ? $email : 'anon:' . $request->ip();
        };

        RateLimiter::for('login', function (Request $request) use ($perUserEmail) {
            return Limit::perMinute(5)
                ->by('login:' . $perUserEmail($request, 'email'))
                ->response(fn () => response()->json(['message' => 'Terlalu banyak percobaan login. Coba lagi nanti.'], 429));
        });

        RateLimiter::for('register', function (Request $request) use ($perUserEmail) {
            return Limit::perMinute(5)
                ->by('register:' . $perUserEmail($request, 'email'))
                ->response(fn () => response()->json(['message' => 'Terlalu banyak percobaan registrasi. Coba lagi nanti.'], 429));
        });

        RateLimiter::for('forgot-password', function (Request $request) use ($perUserEmail) {
            return Limit::perMinute(5)
                ->by('forgot:' . $perUserEmail($request, 'email'))
                ->response(fn () => response()->json(['message' => 'Terlalu banyak permintaan reset. Coba lagi nanti.'], 429));
        });

        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perMinute(5)
                ->by('reset:' . $request->ip())
                ->response(fn () => response()->json(['message' => 'Terlalu banyak percobaan reset. Coba lagi nanti.'], 429));
        });

        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Version::class, VersionPolicy::class);

        // Research Agent: limiter named agar tidak berbagi bucket dgn throttle:10,1 numeric.
        RateLimiter::for('research', function (Request $request) {
            return Limit::perMinute(10)->by('research:'.($request->user()?->id ?: $request->ip()));
        });
    }
}
