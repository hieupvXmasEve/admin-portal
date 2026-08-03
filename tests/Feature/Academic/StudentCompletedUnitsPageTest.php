<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function grantStudentCompletedUnitsPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'student-units_'.Str::lower(Str::random(10))]);

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

it('redirects guests away from the student completed units page', function (): void {
    get(route('academic.reports.student-units.index'))->assertRedirect(route('login'));
});

it('forbids users without view_academic_report from the page', function (): void {
    actingAs(User::factory()->create());

    get(route('academic.reports.student-units.index'))->assertForbidden();
});

it('renders the page with report data and filter options', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantStudentCompletedUnitsPermission($user, $this->campus, 'view_academic_report');

    Student::factory()->create(['campus_id' => $this->campus->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()]);

    get(route('academic.reports.student-units.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/Report/StudentUnits/Index')
            ->has('report.data')
            ->has('filters.options.programs')
            ->has('filters.options.semesters')
            ->has('report.data.0.status')
            // The per-semester breakdown is export-only; shipping it here would
            // bloat every page load with data the table never renders.
            ->missing('report.data.0.units_by_semester'));
});

it('rejects a semester_id that is archived and therefore absent from the dropdown', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantStudentCompletedUnitsPermission($user, $this->campus, 'view_academic_report');

    $archived = Semester::factory()->create(['is_archived' => true]);

    get(route('academic.reports.student-units.index', ['semester_id' => $archived->id]))
        ->assertSessionHasErrors('semester_id');
});

it('rejects an unknown semester_id', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantStudentCompletedUnitsPermission($user, $this->campus, 'view_academic_report');

    get(route('academic.reports.student-units.index', ['semester_id' => 999999]))
        ->assertSessionHasErrors('semester_id');
});

it('ignores a client-supplied campus_id and stays scoped to the session campus', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantStudentCompletedUnitsPermission($user, $this->campus, 'view_academic_report');

    $otherCampus = Campus::factory()->create();
    $otherStudent = Student::factory()->create([
        'campus_id' => $otherCampus->id,
        'student_id' => 'OTHER-CAMPUS-1',
        'intake' => 1,
        'intake_semester_id' => Semester::factory(),
    ]);

    get(route('academic.reports.student-units.index', ['campus_id' => $otherCampus->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.data', fn ($data) => collect($data)->doesntContain(fn ($row) => $row['id'] === $otherStudent->id)));
});
