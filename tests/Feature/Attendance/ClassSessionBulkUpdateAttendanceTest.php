<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn([
        'view_class_session',
        'edit_class_session',
        'edit_course_offering',
    ]);
    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);

    $this->offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
    ]);

    $this->classSession = ClassSession::factory()->create([
        'course_offering_id' => $this->offering->id,
    ]);
});

function createClassSessionAttendance(object $context, ClassSession $classSession, string $status = 'present'): Attendance
{
    return Attendance::create([
        'class_session_id' => $classSession->id,
        'student_id' => Student::factory()->forCampus($context->campus)->create([
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $context->semester->id,
        ])->id,
        'status' => $status,
        'recording_method' => 'manual',
    ]);
}

it('bulk updates attendance for the current class session through an Inertia redirect', function () {
    $first = createClassSessionAttendance($this, $this->classSession);
    $second = createClassSessionAttendance($this, $this->classSession, 'late');

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->from(route('class-sessions.show', $this->classSession))
        ->post(route('class-sessions.attendance.bulk-update', $this->classSession), [
            'attendance_ids' => [$first->id, $second->id],
            'status' => 'absent',
        ])
        ->assertRedirect(route('class-sessions.show', $this->classSession));

    expect($first->fresh()->status)->toBe('absent')
        ->and($second->fresh()->status)->toBe('absent');
});

it('rejects attendance records from another class session', function () {
    $currentAttendance = createClassSessionAttendance($this, $this->classSession);
    $otherSession = ClassSession::factory()->create([
        'course_offering_id' => $this->offering->id,
        'session_date' => '2026-01-16',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);
    $otherAttendance = createClassSessionAttendance($this, $otherSession);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->from(route('class-sessions.show', $this->classSession))
        ->post(route('class-sessions.attendance.bulk-update', $this->classSession), [
            'attendance_ids' => [$currentAttendance->id, $otherAttendance->id],
            'status' => 'absent',
        ])
        ->assertSessionHasErrors('attendance_ids.1');

    expect($currentAttendance->fresh()->status)->toBe('present')
        ->and($otherAttendance->fresh()->status)->toBe('present');
});

it('bulk updates course-offering sessions through the Delivery owner route', function () {
    $secondSession = ClassSession::factory()->create([
        'course_offering_id' => $this->offering->id,
        'session_date' => '2026-01-16',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route('course-offerings.bulk-update-class-sessions', $this->offering), [
            'session_ids' => [$this->classSession->id, $secondSession->id],
            'start_time' => '10:00',
            'end_time' => '12:30',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.updated_count', 2);

    expect($this->classSession->fresh()->start_time->format('H:i:s'))->toBe('10:00:00')
        ->and($this->classSession->fresh()->end_time->format('H:i:s'))->toBe('12:30:00')
        ->and($this->classSession->fresh()->duration_minutes)->toBe(150)
        ->and($secondSession->fresh()->duration_minutes)->toBe(150);
});
