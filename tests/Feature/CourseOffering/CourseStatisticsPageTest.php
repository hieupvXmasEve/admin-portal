<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Characterization coverage for the course-statistics index, per-unit
 * detail, and combined export endpoints before they move off the frozen
 * routes/web/course-statistics.php split file into the owned Academic
 * Delivery module (zero-migration-debt-closure phase 4).
 */
beforeEach(function () {
    $this->withoutMiddleware([PreventRequestForgery::class]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

function mockViewAttendancePermission(): void
{
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn(['view_attendance']);
    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
}

it('denies the course statistics index without view_attendance', function () {
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn([]);
    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get('/course-statistics')
        ->assertForbidden();
});

it('renders the course statistics index with per-unit aggregates', function () {
    mockViewAttendancePermission();

    $unit = Unit::factory()->create(['code' => 'SWE30001', 'name' => 'Software Engineering', 'credit_points' => 6]);
    $offering = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'is_active' => true,
    ]);

    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
        'course_offering_id' => $offering->id,
        'attendance_percentage' => 90.0,
        'final_percentage' => 85.0,
        'final_letter_grade' => 'A',
        'completion_status' => 'completed',
        'grade_points' => 4.0,
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get('/course-statistics')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CourseStatistics/Index')
            ->where('statistics.data.0.unit_code', 'SWE30001')
            ->where('statistics.data.0.total_students', 1)
            ->where('statistics.total', 1)
        );
});

it('rejects an invalid sort field on the course statistics index', function () {
    mockViewAttendancePermission();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get('/course-statistics?sort=not_a_real_column')
        ->assertSessionHasErrors('sort');
});

it('shows per-unit statistics with grade distribution and offerings', function () {
    mockViewAttendancePermission();

    $unit = Unit::factory()->create();
    $offering = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
        'course_offering_id' => $offering->id,
        'grade_status' => 'final',
        'final_letter_grade' => 'A',
        'is_passed' => true,
        'attendance_percentage' => 95.0,
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get("/course-statistics/units/{$unit->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CourseStatistics/UnitDetail')
            ->where('data.unit.id', $unit->id)
            ->where('data.unit.min_attendance_threshold', 80)
            ->where('data.offerings.0.id', $offering->id)
            ->where('data.offerings.0.pass_rate', 100)
        );
});

it('exports combined statistics for a course offering as a file download', function () {
    mockViewAttendancePermission();

    $unit = Unit::factory()->create();
    $offering = CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    $response = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get("/course-statistics/{$offering->id}/export-combined");

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});
