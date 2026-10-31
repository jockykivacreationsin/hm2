<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // The same callbacks are also served WITHOUT the /api prefix (ABDM was registered with the bare /v3/... bridge
            // paths, while this app advertises /api/v3/... for data pushes). Both URL sets must keep working, so the second
            // registration gets its own route names - Laravel cannot cache routes that share a name.
            \Illuminate\Support\Facades\Route::middleware('api')
                ->name('unprefixed.')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'auth'       => \App\Http\Middleware\Authenticate::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'module.permission' => \App\Http\Middleware\ModulePermission::class,
            'abdm.callback' => \App\Http\Middleware\VerifyAbdmCallback::class,
            'kiosk.key'  => \App\Http\Middleware\VerifyKioskKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
