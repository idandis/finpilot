<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // NB: AddLinkHeadersForPreloadedAssets e' volutamente assente. Emetteva un
        // header Link: con un preload per ogni chunk Vite (~90 voci, ~9 KB). Sulla
        // pagina /login il blocco header superava il limite di 10 KB del proxy Aruba,
        // che rispondeva 500 con body vuoto. Gli stessi tag <link rel="modulepreload">
        // sono gia' presenti nell'HTML generato da @vite, quindi non si perde nulla.
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
