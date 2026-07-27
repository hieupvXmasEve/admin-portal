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

function grantGpaFinalizationPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'gpa-finalization_'.Str::lower(Str::random(10))]);

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
    session(['current_campus_id' => $this->campus->id]);
});

it('redirects guests away from the gpa finalization page', function (): void {
    get(route('academic.gpa.finalize.index'))->assertRedirect(route('login'));
});

it('forbids users without view_gpa_finalization from the finalization page', function (): void {
    actingAs(User::factory()->create());

    get(route('academic.gpa.finalize.index'))->assertForbidden();
});

it('forbids users without create_gpa_finalization from finalizing', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaFinalizationPermission($user, $this->campus, 'view_gpa_finalization');

    post(route('academic.gpa.finalize.store'), [
        'semester_id' => Semester::factory()->create()->id,
    ])->assertForbidden();
});

it('renders the finalization page with semester and campus options', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaFinalizationPermission($user, $this->campus, 'view_gpa_finalization');

    Semester::factory()->create(['name' => 'Fall 2026', 'code' => 'FA26']);

    get(route('academic.gpa.finalize.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/Gpa/Index')
            ->has('semesters')
            ->has('campuses')
            ->where('default_campus_id', $this->campus->id));
});

it('rejects finalization without a semester_id', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaFinalizationPermission($user, $this->campus, 'create_gpa_finalization');

    post(route('academic.gpa.finalize.store'), [])
        ->assertSessionHasErrors('semester_id');
});

it('accepts a finalization request for an authorized user with a valid semester', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantGpaFinalizationPermission($user, $this->campus, 'create_gpa_finalization');

    $semester = Semester::factory()->create();

    post(route('academic.gpa.finalize.store'), [
        'semester_id' => $semester->id,
        'campus_id' => $this->campus->id,
    ])->assertSessionDoesntHaveErrors('semester_id');
});
