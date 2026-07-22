<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mcp\Support\ActiveUserMcpActorResolver;
use App\Mcp\Support\McpActorResolver;
use App\Mcp\Support\McpCampusResolver;
use App\Mcp\Support\PermissionMcpCampusResolver;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Lecture;
use App\Models\RoomBooking;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Academic\FacultyWorkforce\Observers\SyncLecturerAccessEligibility;
use App\Modules\Academic\FacultyWorkforce\Support\EloquentLecturerTokenIssuer;
use App\Modules\Admissions\Policies\StudentApplicationPolicy;
use App\Policies\ApiActorPolicy;
use App\Policies\CourseOfferingPolicy;
use App\Shared\Contracts\Identity\LecturerTokenIssuer;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use App\Support\ThemeConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Controlled MCP server identity layer (ADR-0010): the tools resolve the actor and
        // campus through these contracts, so the concrete resolvers bind here.
        $this->app->bind(
            McpActorResolver::class,
            ActiveUserMcpActorResolver::class,
        );
        $this->app->bind(
            McpCampusResolver::class,
            PermissionMcpCampusResolver::class,
        );
        $this->app->bind(LecturerTokenIssuer::class, EloquentLecturerTokenIssuer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        umask(0002);
        Lecture::observe(SyncLecturerAccessEligibility::class);
        $this->registerApiActorGates();
        $this->registerPolicies();
        $this->configureRateLimiting();
        $this->configureMorphMap();
        $this->shareThemeWithViews();
        $this->configureMcpPassport();
        $this->guardDestructiveDatabaseCommands();
    }

    /**
     * `artisan migrate:fresh/migrate:reset/db:wipe --env=testing` does NOT
     * switch databases here — there is no `.env.testing` counterpart file,
     * and Docker Compose injects DB_CONNECTION/DB_DATABASE as real container
     * env vars that Dotenv's immutable loader won't override, so `--env`
     * silently falls straight through to the primary dev database. That
     * wiped it twice already (2026-06-24, 2026-07-03) with no point-in-time
     * recovery (binlog is off).
     *
     * Checked against raw argv rather than the `CommandStarting` event:
     * `--env=testing` makes `Application::runningUnitTests()` return true
     * (it only checks `app()->environment() === 'testing'`, not that PHPUnit
     * is actually running), which makes the console Kernel skip wiring
     * Symfony's command event to Laravel's — so `CommandStarting` silently
     * never fires for a real `--env=testing` invocation. This runs in
     * `boot()` instead, which always executes regardless of `--env`.
     */
    private function guardDestructiveDatabaseCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? null;

        $destructive = ['migrate:fresh', 'migrate:reset', 'db:wipe'];
        if (! in_array($command, $destructive, true)) {
            return;
        }

        $connectionName = config('database.default');
        foreach ($argv as $arg) {
            if (str_starts_with((string) $arg, '--database=')) {
                $connectionName = substr((string) $arg, strlen('--database='));
                break;
            }
        }

        $database = (string) config("database.connections.{$connectionName}.database");
        $protected = (string) env('DB_DATABASE');

        if ($protected !== '' && $database === $protected) {
            throw new RuntimeException(
                "Refusing to run '{$command}' against the protected database '{$database}'. ".
                'Pass --database=testing to target the test database instead (see memory: swinx-env-testing-targets-dev-db).'
            );
        }
    }

    /**
     * Configure Passport for the Controlled MCP server (ADR-0010/0011): a branded
     * consent screen and short-lived access tokens to shrink the orphaned-token
     * window (the active-status gate is the primary offboarding control).
     */
    protected function configureMcpPassport(): void
    {
        Passport::authorizationView('mcp.authorize');
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addHour());
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
        Gate::policy(CourseOffering::class, CourseOfferingPolicy::class);
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

        // Admissions CRM ingestion (ADR-0004): per-caller throttle, tunable via
        // config so the limit can be hardened per environment (and exercised in
        // tests). The throttle middleware reads this each request.
        RateLimiter::for(AdmissionsIngestion::RATE_LIMITER, function (Request $request) {
            $max = (int) config('admissions.ingest.rate_limit.max_attempts', 120);
            $decay = (int) config('admissions.ingest.rate_limit.decay_minutes', 1);
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinutes($decay, $max)->by(AdmissionsIngestion::RATE_LIMITER.':'.$key);
        });

        $this->configureMcpRateLimiting();
    }

    /**
     * Rate limiters for the public Controlled MCP server (ADR-0011).
     *
     * `throttle:mcp` caps tool calls per acting user — the abuse boundary is a person,
     * and a per-client limit could be evaded by registering many clients. `throttle:mcp-oauth`
     * caps the OAuth + dynamic-client-registration routes per IP, with the DCR endpoint
     * (`oauth/register`) held to a far tighter hourly budget since open registration is the
     * registration-spam target. Both are attached at route registration, never globally.
     */
    protected function configureMcpRateLimiting(): void
    {
        RateLimiter::for('mcp', function (Request $request) {
            $max = (int) config('mcp.rate_limits.tool_calls_per_minute', 60);

            // After auth:api runs, the default-guard user is the OAuth actor, so per-user
            // keying is exact; the IP fallback only applies before authentication.
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute($max)->by('mcp-tool:'.$key);
        });

        RateLimiter::for('mcp-oauth', function (Request $request) {
            $ip = (string) $request->ip();

            if ($request->is('oauth/register')) {
                $max = (int) config('mcp.rate_limits.registration_per_hour', 5);

                return Limit::perHour($max)->by('mcp-dcr:'.$ip);
            }

            $max = (int) config('mcp.rate_limits.oauth_per_minute', 10);

            return Limit::perMinute($max)->by('mcp-oauth:'.$ip);
        });
    }
}
