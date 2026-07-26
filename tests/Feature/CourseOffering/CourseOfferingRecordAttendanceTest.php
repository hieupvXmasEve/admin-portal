<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Queries\GetCourseOfferingOperationalStateQuery;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->unit = Unit::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $this->grantPermissions = function (array $permissions): void {
        $permissionService = Mockery::mock(CampusPermissionReader::class);
        $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);

        app()->forgetInstance(CampusPermissionReader::class);
        app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
    };

    ($this->grantPermissions)([
        'view_course_offering',
        'create_course_offering',
    ]);
});

function makeAttendanceOffering(object $context, array $overrides = []): CourseOffering
{
    return CourseOffering::factory()->create(array_merge([
        'semester_id' => $context->semester->id,
        'unit_id' => $context->unit->id,
        'campus_id' => $context->campus->id,
        'course_status' => 'in_progress',
        'enrollment_status' => 'open',
        'current_enrollment' => 0,
        'is_canvas_synced' => false,
    ], $overrides));
}

function makeAttendanceSession(CourseOffering $courseOffering, array $overrides = []): ClassSession
{
    return ClassSession::factory()->create(array_merge([
        'course_offering_id' => $courseOffering->id,
        'status' => 'completed',
        'session_date' => '2026-01-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'session_type' => 'lecture',
        'delivery_mode' => 'in_person',
    ], $overrides));
}

/**
 * Creates a student and confirms them on the offering's active class
 * roster — the same roster relation (CourseOffering::activeClassRosterRegistrations)
 * the endpoint validates student_id against.
 */
function makeAttendanceStudent(object $context, CourseOffering $courseOffering): Student
{
    $student = Student::factory()->forCampus($context->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $context->semester->id,
    ]);

    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $context->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0,
        'is_retake_paid' => 'no',
    ]);

    return $student;
}

function postRecordAttendance(object $context, CourseOffering $courseOffering, ClassSession $classSession, array $records): TestResponse
{
    return actingAs($context->user)
        ->withSession(['current_campus_id' => $context->campus->id])
        ->from(route(CourseOfferingRoutes::SHOW, $courseOffering))
        ->post(route(CourseOfferingRoutes::RECORD_SESSION_ATTENDANCE, [
            'courseOffering' => $courseOffering->id,
            'classSession' => $classSession->id,
        ]), ['records' => $records]);
}

it('returns 403 when the user lacks create_course_offering', function () {
    ($this->grantPermissions)(['view_course_offering']);
    $offering = makeAttendanceOffering($this);
    $session = makeAttendanceSession($offering);
    $student = makeAttendanceStudent($this, $offering);

    postRecordAttendance($this, $offering, $session, [
        ['student_id' => $student->id, 'status' => 'present'],
    ])->assertForbidden();

    expect(Attendance::where('class_session_id', $session->id)->count())->toBe(0);
});

it('rejects an empty records payload', function () {
    $offering = makeAttendanceOffering($this);
    $session = makeAttendanceSession($offering);

    postRecordAttendance($this, $offering, $session, [])
        ->assertSessionHasErrors('records');
});

it('404s when the class session does not belong to the course offering', function () {
    $offering = makeAttendanceOffering($this);
    $otherOffering = makeAttendanceOffering($this);
    $session = makeAttendanceSession($otherOffering);
    $student = makeAttendanceStudent($this, $otherOffering);

    postRecordAttendance($this, $offering, $session, [
        ['student_id' => $student->id, 'status' => 'present'],
    ])->assertNotFound();
});

it('rejects a student who is not on this course offering\'s active roster', function () {
    $offering = makeAttendanceOffering($this);
    $session = makeAttendanceSession($offering);
    $otherOffering = makeAttendanceOffering($this);
    $unrelatedStudent = makeAttendanceStudent($this, $otherOffering);

    postRecordAttendance($this, $offering, $session, [
        ['student_id' => $unrelatedStudent->id, 'status' => 'present'],
    ])->assertSessionHasErrors('records.0.student_id');

    expect(Attendance::where('class_session_id', $session->id)->count())->toBe(0);
});

it('records manual attendance for a roster batch from the cockpit', function () {
    $offering = makeAttendanceOffering($this);
    $session = makeAttendanceSession($offering);
    $present = makeAttendanceStudent($this, $offering);
    $absent = makeAttendanceStudent($this, $offering);

    postRecordAttendance($this, $offering, $session, [
        ['student_id' => $present->id, 'status' => 'present'],
        ['student_id' => $absent->id, 'status' => 'absent', 'notes' => 'No show'],
    ])->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    $this->assertDatabaseHas('attendances', [
        'class_session_id' => $session->id,
        'student_id' => $present->id,
        'status' => 'present',
        'recording_method' => 'manual',
    ]);
    $this->assertDatabaseHas('attendances', [
        'class_session_id' => $session->id,
        'student_id' => $absent->id,
        'status' => 'absent',
        'recording_method' => 'manual',
        'notes' => 'No show',
    ]);
});

it('confirms an auto-system attendance record manually instead of failing on the unique constraint', function () {
    $offering = makeAttendanceOffering($this);
    $session = makeAttendanceSession($offering);
    $student = makeAttendanceStudent($this, $offering);

    Attendance::create([
        'class_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'present',
        'recording_method' => 'auto_system',
    ]);

    postRecordAttendance($this, $offering, $session, [
        ['student_id' => $student->id, 'status' => 'late'],
    ])->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    expect(Attendance::where('class_session_id', $session->id)->where('student_id', $student->id)->count())->toBe(1);
    $this->assertDatabaseHas('attendances', [
        'class_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'late',
        'recording_method' => 'manual',
    ]);
});

it('clears the sessions_missing_attendance blocker once attendance is recorded for every session', function () {
    ($this->grantPermissions)(['view_course_offering', 'create_course_offering', 'complete_course_offering']);
    $offering = makeAttendanceOffering($this);
    $first = makeAttendanceSession($offering, ['session_title' => 'Week 1', 'sequence_number' => 1]);
    $second = makeAttendanceSession($offering, ['session_title' => 'Week 2', 'sequence_number' => 2]);
    $student = makeAttendanceStudent($this, $offering);

    postRecordAttendance($this, $offering, $first, [
        ['student_id' => $student->id, 'status' => 'present'],
    ])->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    $stateAfterFirst = GetCourseOfferingOperationalStateQuery::handle($offering->fresh(), $this->user);
    expect(array_column($stateAfterFirst['readiness_blockers'], 'code'))->toContain('sessions_missing_attendance');

    postRecordAttendance($this, $offering, $second, [
        ['student_id' => $student->id, 'status' => 'present'],
    ])->assertRedirect(route(CourseOfferingRoutes::SHOW, $offering));

    $stateAfterSecond = GetCourseOfferingOperationalStateQuery::handle($offering->fresh(), $this->user);
    expect(array_column($stateAfterSecond['readiness_blockers'], 'code'))->not->toContain('sessions_missing_attendance');
    expect(array_column($stateAfterSecond['available_actions'], 'allowed'))->toBe([true]);
});
