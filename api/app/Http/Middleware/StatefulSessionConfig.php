<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class StatefulSessionConfig extends EnsureFrontendRequestsAreStateful
{
    protected function configureSecureCookieSessions(): void
    {
        config([
            'session.http_only' => env('SESSION_HTTP_ONLY', true),
            'session.same_site' => env('SESSION_SAME_SITE', 'none'),
        ]);
    }
}
