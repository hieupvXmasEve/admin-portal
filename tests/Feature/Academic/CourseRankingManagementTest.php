<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\CourseOffering;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function grantCourseRankingPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'course-ranking_'.Str::lower(Str::random(10))]);

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

function createGradedRecord(Campus $campus, Semester $semester, Unit $unit, string $fullName, float $finalPercentage): AcademicRecord
{
    $student = Student::factory()->forCampus($campus)->create([
        'full_name' => $fullName,
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    $offering = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'is_active' => true,
    ]);

    return AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
        'course_offering_id' => $offering->id,
        'final_percentage' => $finalPercentage,
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

it('redirects guests away from the course ranking page', function (): void {
    get(route('academic.course-ranking.index'))->assertRedirect(route('login'));
});

it('forbids users without view_academic_report from the ranking page', function (): void {
    actingAs(User::factory()->create());

    get(route('academic.course-ranking.index'))->assertForbidden();
});

it('ranks students by final percentage within the requested semester and campus', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantCourseRankingPermission($user, $this->campus, 'view_academic_report');

    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create(['code' => 'CS101', 'name' => 'Intro to CS']);

    createGradedRecord($this->campus, $semester, $unit, 'Top Student', 95.0);
    createGradedRecord($this->campus, $semester, $unit, 'Second Student', 80.0);

    get(route('academic.course-ranking.index', ['semester_id' => $semester->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/CourseRanking/Index')
            ->where('ranking.semester_id', $semester->id)
            ->where('ranking.courses.0.unit.code', 'CS101')
            ->where('ranking.courses.0.students.0.full_name', 'TOP STUDENT')
            ->where('ranking.courses.0.students.1.full_name', 'SECOND STUDENT'));
});

it('excludes egc units from the ranking', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantCourseRankingPermission($user, $this->campus, 'view_academic_report');

    $semester = Semester::factory()->create();
    $egcUnit = Unit::factory()->create(['unit_type' => 'egc']);

    createGradedRecord($this->campus, $semester, $egcUnit, 'EGC Student', 90.0);

    get(route('academic.course-ranking.index', ['semester_id' => $semester->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('ranking.courses', []));
});
