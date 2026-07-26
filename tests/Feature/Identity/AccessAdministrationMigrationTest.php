<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Lecture;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Identity\Actions\SyncUserCampusRolesAction;
use App\Modules\Identity\Queries\GetUsersQuery;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->administrator = User::factory()->create();
    $administratorRole = Role::factory()->create();
    $permissions = collect(['view_user', 'edit_user', 'view_role', 'create_role', 'edit_role', 'delete_role'])
        ->map(fn (string $code): Permission => Permission::factory()->create(['code' => $code]));

    $administratorRole->permissions()->attach($permissions->pluck('id'));
    $this->administrator->campusRoles()->attach($administratorRole, ['campus_id' => $this->campus->id]);
    $this->withSession(['current_campus_id' => $this->campus->id, '_token' => 'identity-access-test-token']);
});

it('invalidates a revoked campus permission cache entry', function (): void {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['code' => 'manage_students']);
    $role->permissions()->attach($permission);
    $user->campusRoles()->attach($role, ['campus_id' => $this->campus->id]);

    $reader = app(CampusPermissionReader::class);
    $reader->forgetPermissionCodesForUserId((int) $user->id, [(int) $this->campus->id]);
    expect($reader->permissionCodesForUserId((int) $user->id, (int) $this->campus->id))
        ->toContain('manage_students');

    SyncUserCampusRolesAction::run([
        'user_id' => (int) $user->id,
        'campus_id' => (int) $this->campus->id,
        'role_ids' => [],
    ]);

    expect($reader->permissionCodesForUserId((int) $user->id, (int) $this->campus->id))
        ->not->toContain('manage_students');
});

it('creates and updates a role with its permissions through the established staff routes', function (): void {
    $permission = Permission::factory()->create(['module' => 'identity']);

    $this->actingAs($this->administrator)
        ->withHeader('X-CSRF-TOKEN', 'identity-access-test-token')
        ->post(route('roles.store'), [
            'name' => 'Identity operator',
            'permissions' => [$permission->id],
        ])
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('inertia.flash_data.success', 'Role created successfully.');

    $role = Role::query()->where('name', 'Identity operator')->firstOrFail();

    expect($role->permissions()->pluck('permissions.id')->all())->toBe([$permission->id]);
    expect(DB::table('activity_log')
        ->where('subject_type', Role::class)
        ->where('subject_id', $role->id)
        ->where('causer_id', $this->administrator->id)
        ->where('description', 'Created role: Identity operator')
        ->exists())->toBeTrue();

    $this->actingAs($this->administrator)
        ->withHeader('X-CSRF-TOKEN', 'identity-access-test-token')
        ->put(route('roles.update', $role), [
            'name' => 'Identity administrator',
            'permissions' => [],
        ])
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('inertia.flash_data.success', 'Role updated successfully.');

    expect($role->fresh()->name)->toBe('Identity administrator')
        ->and($role->permissions()->count())->toBe(0);
});

it('keeps normalized role codes unique through the established staff route', function (): void {
    Role::factory()->create(['name' => 'A B', 'code' => 'a_b']);

    $this->actingAs($this->administrator)
        ->withHeader('X-CSRF-TOKEN', 'identity-access-test-token')
        ->post(route('roles.store'), [
            'name' => 'A-B',
            'permissions' => [],
        ])
        ->assertRedirect(route('roles.index'));

    expect(Role::query()->where('name', 'A-B')->value('code'))->toBe('a_b_1');
});

it('uses the current request campus for user role filters and edit selections', function (): void {
    $role = Role::factory()->create(['name' => 'Campus operator']);
    $otherCampus = Campus::factory()->create();
    $otherCampusRole = Role::factory()->create(['name' => 'Other campus operator']);
    $staff = User::factory()->create();

    $staff->campusRoles()->attach($role, ['campus_id' => $this->campus->id]);
    $staff->campusRoles()->attach($otherCampusRole, ['campus_id' => $otherCampus->id]);

    app()->instance(GetUsersQuery::class, new GetUsersQuery);

    $this->actingAs($this->administrator)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('identity.users.index', ['role_id' => $role->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.id', $staff->id)
            ->where('users.data.0.campus_roles.0.id', $role->id));

    $this->actingAs($this->administrator)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('identity.users.edit', $staff))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Users/Edit')
            ->where('userRoleIds', [$role->id]));
});

it('deletes a role after removing its campus user assignments', function (): void {
    $role = Role::factory()->create();
    $staff = User::factory()->create();
    $staff->campusRoles()->attach($role, ['campus_id' => $this->campus->id]);

    $this->actingAs($this->administrator)
        ->withHeader('X-CSRF-TOKEN', 'identity-access-test-token')
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse()
        ->and(DB::table('campus_user_roles')->where('role_id', $role->id)->exists())->toBeFalse();
});

it('issues a student impersonation token with the established API envelope and audit evidence', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'email' => 'student-impersonation@example.test',
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);

    Log::spy();

    $response = $this->actingAs($this->administrator)
        ->withHeader('X-CSRF-TOKEN', 'identity-access-test-token')
        ->postJson(route('api.admin.students.impersonate'), [
            'email' => $student->email,
            'purpose' => 'support',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.student.id', $student->id)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.impersonation_info.impersonated_by.id', $this->administrator->id)
        ->assertJsonPath('message', 'Student impersonation token generated successfully');

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Admin student impersonation', Mockery::on(fn (array $context): bool => $context['admin_id'] === $this->administrator->id && $context['student_id'] === $student->id));
});

it('issues a lecturer impersonation token with the established API envelope and audit evidence', function (): void {
    $lecturer = Lecture::factory()->create([
        'email' => 'lecturer-impersonation@example.test',
        'employment_status' => 'active',
        'is_active' => true,
    ]);

    Log::spy();

    $response = $this->actingAs($this->administrator)
        ->withHeader('X-CSRF-TOKEN', 'identity-access-test-token')
        ->postJson(route('api.admin.lecturers.impersonate'), [
            'email' => $lecturer->email,
            'purpose' => 'support',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.lecturer.id', $lecturer->id)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.impersonation_info.impersonated_by.id', $this->administrator->id)
        ->assertJsonPath('message', 'Lecturer impersonation token generated successfully');

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Admin lecturer impersonation', Mockery::on(fn (array $context): bool => $context['admin_id'] === $this->administrator->id && $context['lecturer_id'] === $lecturer->id));
});

it('preserves the student impersonation validation envelope for an inactive profile', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'email' => 'inactive-student@example.test',
        'status' => 'inactive',
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);

    $this->actingAs($this->administrator)
        ->withHeader('X-CSRF-TOKEN', 'identity-access-test-token')
        ->postJson(route('api.admin.students.impersonate'), ['email' => $student->email])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Cannot impersonate inactive student. Student status: inactive');
});

it('keeps impersonation available to an authenticated administrator without changing its permission boundary', function (): void {
    $unprivilegedStaff = User::factory()->create();

    $this->actingAs($unprivilegedStaff)
        ->withHeader('X-CSRF-TOKEN', 'identity-access-test-token')
        ->getJson(route('api.admin.students.impersonation-sessions'))
        ->assertOk()
        ->assertJsonPath('success', true);
});
