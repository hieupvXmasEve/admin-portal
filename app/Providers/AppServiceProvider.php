<?php

namespace App\Providers;

use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Lecture;
use App\Models\RoomBooking;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Models\User;
use App\Policies\ApiActorPolicy;
use App\Policies\StudentApplicationPolicy;
use App\Support\ThemeConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        umask(0002);
        $this->registerApiActorGates();
        $this->registerPolicies();
        $this->configureRateLimiting();
        $this->configureMorphMap();
        $this->shareThemeWithViews();
    }

    protected function shareThemeWithViews(): void
    {
        View::share('themeName', ThemeConfig::activeName());
        View::share('themeVariables', ThemeConfig::variables());
    }

    protected function registerApiActorGates(): void
    {
        Gate::define('api.actor.student_or_parent', [ApiActorPolicy::class, 'accessStudentOrParent']);
        Gate::define('api.actor.parent', [ApiActorPolicy::class, 'accessParent']);
        Gate::define('api.actor.lecturer', [ApiActorPolicy::class, 'accessLecturer']);
    }

    /**
     * Register model policies explicitly (this codebase wires authorization in
     * providers rather than relying on convention-based auto-discovery).
     */
    protected function registerPolicies(): void
    {
        Gate::policy(StudentApplication::class, StudentApplicationPolicy::class);
    }

    /**
     * Configure polymorphic relationship type mapping.
     * Using morphMap() instead of enforceMorphMap() to only map specific types
     * without requiring all models to be mapped.
     */
    protected function configureMorphMap(): void
    {
        Relation::morphMap([
            RoomBooking::BOOKED_BY_USER => User::class,
            RoomBooking::BOOKED_BY_STUDENT => Student::class,
            RoomBooking::BOOKED_BY_LECTURER => User::class, // Lecturer is typically a User with lecturer role
            RoomBooking::BOOKED_BY_LECTURE => Lecture::class,
            'course' => CourseOffering::class,
            'semester' => Semester::class,
            'department' => Department::class,
        ]);
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Upload rate limiters
        RateLimiter::for('uploads-single', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many upload attempts. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('uploads-multiple', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many multiple upload attempts. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('uploads-validate', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many validation requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('uploads-list', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many list requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('uploads-info', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many info requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('uploads-delete', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many delete requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('uploads-serve', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many file serve requests. Please try again later.',
                        'retry_after' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // Notification template test-send: limit admin self-testing to 5 per minute (C2).
        RateLimiter::for('notification-template-test-send', fn (Request $r) => Limit::perMinute(5)->by($r->user()?->id ?: $r->ip()));
    }
}
