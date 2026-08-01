<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const GOLD_TEST_CSRF = 'gold-adjust-route-test-csrf';

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);

    $campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $campus);
    session([
        '_token' => GOLD_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);

    $semester = Semester::factory()->create();
    $this->student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);
});

function grantRole(User $user, string $roleCode): void
{
    $role = Role::where('code', $roleCode)->firstOrFail();

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id,
        'campus_id' => app('campus')->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);
}

function adjustGold(Student $student, array $payload): TestResponse
{
    return test()->post(
        route('api.admin.wallet.students.adjust', $student),
        array_merge(['_token' => GOLD_TEST_CSRF], $payload)
    );
}

it('gates the gold adjust route with can:adjust_gold_wallet and registers the slug', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($r) => $r->getName() === 'api.admin.wallet.students.adjust');

    expect($route)->not->toBeNull();

    $slugs = collect($route->gatherMiddleware())
        ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'can:'))
        ->map(fn (string $m) => explode(',', substr($m, 4))[0]);

    expect($slugs)->toContain('adjust_gold_wallet');

    $registered = collect(config('permission.access'))->flatten()->all();
    expect($registered)->toContain('adjust_gold_wallet');
});

it('rejects a staff user without the adjust_gold_wallet grant', function () {
    $user = User::factory()->create();
    // No role grant -> no permission.
    $response = $this->actingAs($user)->post(
        route('api.admin.wallet.students.adjust', $this->student),
        ['_token' => GOLD_TEST_CSRF, 'amount' => 50, 'notes' => 'test grant']
    );

    $response->assertForbidden();
});

it('allows a super admin to adjust and writes an integer ledger entry', function () {
    $user = User::factory()->create();
    grantRole($user, 'super_admin');

    $response = $this->actingAs($user)->post(
        route('api.admin.wallet.students.adjust', $this->student),
        ['_token' => GOLD_TEST_CSRF, 'amount' => 50, 'notes' => 'test grant']
    );

    $response->assertOk();
    $this->assertDatabaseHas('gold_transactions', [
        'student_id' => $this->student->id,
        'amount' => 50,
        'type' => 'adjust',
        'performed_by' => $user->id,
    ]);
});
