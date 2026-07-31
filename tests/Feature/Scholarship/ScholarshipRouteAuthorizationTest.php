<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

const SCHOLARSHIP_TEST_CSRF = 'scholarship-route-test-csrf';

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $campus);
    session([
        '_token' => SCHOLARSHIP_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);
});

/**
 * All routes registered by the two scholarship route files, keyed by name.
 *
 * @return array<string, Illuminate\Routing\Route>
 */
function scholarshipRoutes(): array
{
    $routes = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name !== null && (str_starts_with($name, 'scholarships.') || str_starts_with($name, 'student-scholarships.'))) {
            $routes[$name] = $route;
        }
    }

    return $routes;
}

function makeScholarshipSuperAdmin(): User
{
    $user = User::factory()->create();
    $role = Role::where('code', 'super_admin')->firstOrFail();

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id,
        'campus_id' => app('campus')->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);

    return $user;
}

/** Route params for the model-bound scholarship routes. */
function scholarshipRouteParams(): array
{
    $definition = ScholarshipDefinition::create([
        'code' => 'TEST50',
        'name' => 'Test Scholarship',
        'type' => 'percentage',
        'amount' => 50,
        'valid_from' => now()->subYear(),
        'valid_until' => now()->addYear(),
        'is_active' => true,
    ]);

    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);

    $award = StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now(),
    ]);

    return [
        'scholarship' => $definition->id,
        'studentScholarship' => $award->id,
    ];
}

it('gates every route in both scholarship route files with a can middleware', function () {
    $routes = scholarshipRoutes();

    expect($routes)->not->toBeEmpty();

    // A typo'd slug also 403s everyone (no Gate::before), so every gate slug
    // must additionally exist in the permission registry config.
    $registeredSlugs = collect(config('permission.access'))->flatten()->all();

    $ungated = [];
    $unregistered = [];

    foreach ($routes as $name => $route) {
        $slugs = collect($route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))
            ->map(fn (string $middleware) => explode(',', substr($middleware, 4))[0]);

        if ($slugs->isEmpty()) {
            $ungated[] = $name;
        }

        foreach ($slugs as $slug) {
            if (! in_array($slug, $registeredSlugs, true)) {
                $unregistered[] = "{$name} => {$slug}";
            }
        }
    }

    expect($ungated)->toBe([]);
    expect($unregistered)->toBe([]);
});

it('returns 403 for a user without grants on every scholarship route including imports', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $params = scholarshipRouteParams();

    foreach (scholarshipRoutes() as $name => $route) {
        $method = strtolower($route->methods()[0]);
        $url = route($name, $params);

        $payload = $method === 'get' ? [] : ['_token' => SCHOLARSHIP_TEST_CSRF];

        $response = $method === 'get'
            ? $this->actingAs($user)->get($url)
            : $this->actingAs($user)->{$method}($url, $payload);

        expect($response->getStatusCode())->toBe(403, "Route {$name} expected 403, got {$response->getStatusCode()}");
    }
});

it('lets super admin reach the gated index pages', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = makeScholarshipSuperAdmin();

    $this->actingAs($user)->get(route('scholarships.index'))->assertOk();
    $this->actingAs($user)->get(route('student-scholarships.index'))->assertOk();
    $this->actingAs($user)->get(route('student-scholarships.imports.index'))->assertOk();
});

it('lets an explicitly granted role reach a gated route', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'scholarship_viewer'], ['name' => 'Scholarship Viewer']);
    $permission = Permission::where('code', 'view_scholarship')->firstOrFail();

    DB::table('role_permissions')->insert([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id,
        'campus_id' => app('campus')->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);

    $this->actingAs($user)->get(route('student-scholarships.index'))->assertOk();

    // Granted role still lacks the import permission — imports stay closed.
    $this->actingAs($user)->get(route('student-scholarships.imports.index'))->assertForbidden();
});
