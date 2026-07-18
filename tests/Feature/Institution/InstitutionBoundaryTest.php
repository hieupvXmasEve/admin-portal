<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Department;
use App\Models\DepartmentMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Modules\Academic\Support\LifecycleFormOptions;
use App\Modules\Institution\Actions\AddDepartmentMemberAction;
use App\Modules\Institution\Actions\CreateCampusAction;
use App\Modules\Institution\Actions\RemoveDepartmentMemberAction;
use App\Modules\Institution\Actions\UpdateDepartmentMemberAction;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function grantInstitutionPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'institution_'.Str::lower(Str::random(10))]);

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

it('publishes neutral campus references to the Academic lifecycle selection flow', function (): void {
    $campus = Campus::factory()->create([
        'name' => 'Hanoi Campus',
        'code' => 'HN',
    ]);

    $references = app(CampusReferenceReader::class)->all();
    $options = app(LifecycleFormOptions::class)->actionOptions();

    expect($references)->toHaveCount(1)
        ->and($references[0])->toBeInstanceOf(CampusReference::class)
        ->and($references[0]->id)->toBe($campus->id)
        ->and($options['campuses'])->toBe([
            ['id' => $campus->id, 'name' => 'Hanoi Campus', 'code' => 'HN'],
        ]);
});

it('creates a campus through the Institution owner action', function (): void {
    $campus = CreateCampusAction::run([
        'name' => 'Da Nang Campus',
        'code' => 'DN',
        'address' => 'Da Nang',
    ]);

    expect($campus->id)->not->toBeNull();

    $this->assertDatabaseHas('campuses', [
        'id' => $campus->id,
        'name' => 'Da Nang Campus',
        'code' => 'DN',
    ]);
});

it('manages department membership through Institution owner actions', function (): void {
    $department = Department::factory()->create();
    $user = User::factory()->create();

    $membership = AddDepartmentMemberAction::run([
        'department_id' => $department->id,
        'user_id' => $user->id,
        'department_role' => 'head',
    ]);

    UpdateDepartmentMemberAction::run([
        'department_id' => $department->id,
        'membership_id' => $membership->id,
        'department_role' => 'staff',
        'is_active' => false,
    ]);

    expect($membership->fresh())
        ->department_role->toBe('staff')
        ->is_active->toBeFalse();

    RemoveDepartmentMemberAction::run([
        'department_id' => $department->id,
        'membership_id' => $membership->id,
    ]);

    expect(DepartmentMembership::query()->find($membership->id))->toBeNull();
});

it('preserves campus administration URLs and authorization through Institution', function (): void {
    $currentCampus = Campus::factory()->create();
    $staff = User::factory()->create();
    grantInstitutionPermission($staff, $currentCampus, 'view_campus');
    grantInstitutionPermission($staff, $currentCampus, 'create_campus');
    grantInstitutionPermission($staff, $currentCampus, 'edit_campus');

    session(['current_campus_id' => $currentCampus->id]);

    $this->actingAs($staff)
        ->get(route('campuses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Campuses/Index'));

    $this->actingAs($staff)
        ->post(route('campuses.store'), [
            'name' => 'Can Tho Campus',
            'code' => 'CT',
            'address' => 'Can Tho',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('campuses', [
        'name' => 'Can Tho Campus',
        'code' => 'CT',
    ]);

    $managedCampus = Campus::query()->where('code', 'CT')->firstOrFail();

    $this->actingAs($staff)
        ->put(route('campuses.update', $managedCampus), [
            'name' => 'Can Tho Main Campus',
            'code' => 'CT',
            'address' => 'Can Tho',
        ])
        ->assertRedirect();

    expect($managedCampus->fresh()->name)->toBe('Can Tho Main Campus');

    $this->actingAs($staff)
        ->get(route('campuses.index', ['search' => 'Can Tho', 'per_page' => 5]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('campuses.data', 1)
            ->where('campuses.data.0.code', 'CT')
            ->where('filters.search', 'Can Tho'));

    $this->actingAs($staff)
        ->get(route('campuses.index', ['per_page' => 101]))
        ->assertSessionHasErrors('per_page');
});

it('preserves department membership administration URLs through Institution', function (): void {
    $campus = Campus::factory()->create();
    $staff = User::factory()->create();
    $candidate = User::factory()->create();
    $department = Department::factory()->create();
    grantInstitutionPermission($staff, $campus, 'manage_departments');

    session(['current_campus_id' => $campus->id]);

    $this->actingAs($staff)
        ->get(route('admin.departments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Departments/Index'));

    $this->actingAs($staff)
        ->post(route('admin.departments.members.store', $department), [
            'user_id' => $candidate->id,
            'department_role' => 'staff',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('department_memberships', [
        'department_id' => $department->id,
        'user_id' => $candidate->id,
        'department_role' => 'staff',
    ]);
});
