<?php

use App\Http\Middleware\AppGuard;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'perm' => EnsurePermission::class,
            'guard.app' => AppGuard::class,
        ]);
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectGuestsTo(fn (Request $r) => route('staff.login'));
        $middleware->redirectUsersTo(fn (Request $r) => route('dashboard'));
        $middleware->trustProxies(at: '*');
        // Blunt bot/scraper floods and runaway scripts before they can push shared-hosting CPU/process limits to 100%.
        // Generous enough for a small office sharing one NAT IP (chat polling, dashboards, exports) to never notice it.
        // Distinct prefix so this shared counter never collides with the tighter per-route throttles (e.g. login, lead form),
        // which otherwise resolve to the same cache key for guests (domain+IP only, no route) and would double-count hits.
        $middleware->web(append: ['throttle:600,1,global']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());
    })->create();
