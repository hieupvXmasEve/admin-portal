<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
});

it('rejects unauthenticated requests to the academic report api', function (): void {
    getJson(route('api.admin.academic-reports.index'))->assertUnauthorized();
});

it('forbids users without view_academic_report from the academic report api', function (): void {
    actingAs(User::factory()->create());

    getJson(route('api.admin.academic-reports.index'))->assertForbidden();
});

it('returns the academic report envelope for an authorized user', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $permission = Permission::firstOrCreate(
        ['code' => 'view_academic_report'],
        ['name' => 'view_academic_report'],
    );
    $role = Role::factory()->create(['code' => 'academic-report-api']);
    RolePermission::create(['role_id' => $role->id, 'permission_id' => $permission->id]);
    CampusUserRole::create(['user_id' => $user->id, 'campus_id' => $this->campus->id, 'role_id' => $role->id]);

    $semester = Semester::factory()->create();

    getJson(route('api.admin.academic-reports.index', ['semester_id' => $semester->id]))
        ->assertOk()
        ->assertJsonStructure(['success', 'data', 'timestamp']);
});
