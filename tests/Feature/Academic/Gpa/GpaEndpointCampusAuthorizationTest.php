<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

// Grants $permissionCode at $campus, which also makes $user a member of $campus
// (the campus_user_roles row is what the FormRequest authorize() gate reads).
function grantGpaPermissionAt(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'gpa-campus-authz_'.Str::lower(Str::random(10))]);

    RolePermission::create([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);
    CampusUserRole::create([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
    ]);
}

beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
});

it('forbids a campus member from previewing another campus by campus_id', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaPermissionAt($user, $this->campus, 'view_gpa_finalization');

    get(route('academic.gpa.finalize.index', ['campus_id' => $this->otherCampus->id]))
        ->assertForbidden();
});

it('forbids a campus member from finalizing another campus by campus_id', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaPermissionAt($user, $this->campus, 'create_gpa_finalization');

    post(route('academic.gpa.finalize.store'), [
        'semester_id' => Semester::factory()->create()->id,
        'campus_id' => $this->otherCampus->id,
    ])->assertForbidden();
});

it('allows a member to preview their own campus by campus_id', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaPermissionAt($user, $this->campus, 'view_gpa_finalization');

    get(route('academic.gpa.finalize.index', ['campus_id' => $this->campus->id]))
        ->assertOk();
});

it('allows a member to finalize their own campus by campus_id', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaPermissionAt($user, $this->campus, 'create_gpa_finalization');

    post(route('academic.gpa.finalize.store'), [
        'semester_id' => Semester::factory()->create()->id,
        'campus_id' => $this->campus->id,
    ])->assertSessionDoesntHaveErrors('semester_id')->assertRedirect();
});

it('falls back to the session campus when campus_id is omitted on preview', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaPermissionAt($user, $this->campus, 'view_gpa_finalization');

    get(route('academic.gpa.finalize.index'))->assertOk();
});

it('falls back to the session campus when campus_id is omitted on finalize', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaPermissionAt($user, $this->campus, 'create_gpa_finalization');

    post(route('academic.gpa.finalize.store'), [
        'semester_id' => Semester::factory()->create()->id,
    ])->assertSessionDoesntHaveErrors('semester_id')->assertRedirect();
});
