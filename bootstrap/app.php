<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckCampusSelected;
use App\Http\Middleware\CheckPermissions;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCampus;
use App\Http\Middleware\StudentMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CheckCampusSelected::class,
            SetCampus::class,
        ]);

        // Register route middleware aliases
        $middleware->alias([
            'permissions' => CheckPermissions::class,
            'admin' => AdminMiddleware::class,
            'student' => StudentMiddleware::class,
            'campus.selected' => CheckCampusSelected::class,
            'student.api.auth' => \App\Http\Middleware\StudentApiAuthorization::class,
            'student.api.rate' => \App\Http\Middleware\StudentApiRateLimiter::class,
            'lecturer.api.auth' => \App\Http\Middleware\LecturerApiAuthorization::class,
            'lecturer.api.rate' => \App\Http\Middleware\LecturerApiRateLimiter::class,
            'api.logging' => \App\Http\Middleware\ApiLogging::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            // Handle API exceptions with custom handler
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(\App\Exceptions\ApiExceptionHandler::class)->handle($e, $request);
            }
        });
    })->create();
