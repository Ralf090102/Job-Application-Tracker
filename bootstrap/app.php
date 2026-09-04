<?php

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
        // Phase 6: makes requests from FRONTEND_URL "stateful" — session
        // cookie + CSRF instead of bearer tokens. This is what Sanctum's
        // SPA auth mode actually is; see Roadmap.md's Architecture section
        // for why that's the chosen pattern over token auth.
        $middleware->statefulApi();

        // This app has no web login route at all — logging in happens
        // entirely through POST /api/login, never a redirect. Without this,
        // Laravel's default Authenticate middleware tries to redirect an
        // unauthenticated guest to a route named 'login' whenever the
        // request doesn't explicitly send Accept: application/json — a
        // route that doesn't exist here, which crashes with a 500 instead
        // of a clean 401. Found live via a plain curl call with no Accept
        // header; the React frontend always sends one, so it never hit this
        // in the browser, but a pure JSON API shouldn't depend on every
        // caller setting that header correctly to fail gracefully.
        $middleware->redirectGuestsTo(fn () => null);

        // v2 Phase 2: shared-secret guard for the machine-to-machine
        // auto-apply ingest endpoint (see
        // App\Http\Middleware\VerifyAutoApplyIngestToken).
        $middleware->alias([
            'auto-apply.token' => \App\Http\Middleware\VerifyAutoApplyIngestToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
