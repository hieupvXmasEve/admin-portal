<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Admissions\AuditAdmissionsIngest;
use App\Http\Middleware\Admissions\AuthorizeAdmissionsIngest;
use App\Http\Middleware\ApiActorAuthorize;
use App\Http\Middleware\ApiLogging;
use App\Http\Middleware\CheckCampusSelected;
use App\Http\Middleware\EitherMiddleware;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LecturerApiAuthorization;
use App\Http\Middleware\LecturerApiRateLimiter;
use App\Http\Middleware\ParentStudentAccess;
use App\Http\Middleware\SetCampus;
use App\Http\Middleware\StudentApiAuthorization;
use App\Http\Middleware\StudentApiRateLimiter;
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
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'api/webhooks/dng/*',
            // Unauthenticated MCP discovery + dynamic client registration are called by
            // external agent clients with no session, so they must bypass CSRF (a POST
            // to oauth/register would otherwise 419). See ADR-0011 / routes/web.php.
            'oauth/register',
            '.well-known/*',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CheckCampusSelected::class,
            SetCampus::class,
        ]);

        // Register route middleware aliases
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'student' => StudentMiddleware::class,
            'campus.selected' => CheckCampusSelected::class,
            'student.api.auth' => StudentApiAuthorization::class,
            'student.api.rate' => StudentApiRateLimiter::class,
            'lecturer.api.auth' => LecturerApiAuthorization::class,
            'lecturer.api.rate' => LecturerApiRateLimiter::class,
            'parent.student.access' => ParentStudentAccess::class,
            'api.actor' => ApiActorAuthorize::class,
            'api.logging' => ApiLogging::class,
            'either' => EitherMiddleware::class,
            'admissions.ingest' => AuthorizeAdmissionsIngest::class,
            'admissions.audit' => AuditAdmissionsIngest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            // Handle API exceptions with custom handler
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(ApiExceptionHandler::class)->handle($e, $request);
            }
        });
    })->create();
