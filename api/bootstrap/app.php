<?php

use App\Http\Middleware\AuthenticateProjectToken;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\StartSessionIfStateless;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role.admin' => EnsureUserIsAdmin::class,
            'auth.project-token' => AuthenticateProjectToken::class,
        ]);
        $middleware->api(prepend: [
            StartSessionIfStateless::class,
            EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->trustProxies(
            at: ['api', 'nginx', 'localhost', '127.0.0.1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('sanctum/*'),
        );
    })->create();
