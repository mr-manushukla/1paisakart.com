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
        $middleware->statefulApi(); // Sanctum SPA cookie auth for stateful domains

        // Private-site gate, on BOTH groups so the API can't be used to walk
        // around it. Dormant unless SITE_PASSWORD is set.
        $middleware->web(append: [\App\Http\Middleware\SitePassword::class]);
        $middleware->api(append: [\App\Http\Middleware\SitePassword::class]);

        // The unlock pass is already a keyed HMAC, and it has to be readable by
        // the api group — which has no cookie decryption — so leave it in clear.
        $middleware->encryptCookies(except: [\App\Http\Middleware\SitePassword::COOKIE]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(fn (\App\Exceptions\BusinessException $e) => response()->json(
            ['message' => $e->getMessage()], 422,
        ));
    })->create();
