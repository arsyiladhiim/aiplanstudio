<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the session/cookie middleware stack only for non-stateful requests.
 *
 * For stateful requests (browser with a matching Origin/Referer) Sanctum's
 * EnsureFrontendRequestsAreStateful already applies its own EncryptCookies +
 * StartSession stack, so running this stack too would decrypt the session
 * cookie twice and rotate the session on every request. Stateless clients
 * (CLI, tests without Origin/Referer) still need the session middleware here.
 */
class StartSessionIfStateless
{
    public function handle(Request $request, Closure $next): Response
    {
        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            return $next($request);
        }

        return (new Pipeline(app()))->send($request)->through([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
        ])->then($next);
    }
}
