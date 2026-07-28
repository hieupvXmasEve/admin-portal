<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Characterization coverage for the two routed student attendance endpoints,
 * before the chain behind them stops passing the Student model around.
 */
beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create([
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]);

    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    $this->offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    CourseRegistration::query()->create([
        'student_id' => $this->student->id,
        'course_offering_id' => $this->offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'registration_method' => 'online',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0,
        'is_retake_paid' => 'no',
    ]);

    $session = ClassSession::factory()->create([
        'course_offering_id' => $this->offering->id,
        'session_date' => '2026-03-16',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'duration_minutes' => 120,
        'session_type' => 'lecture',
    ]);

    Attendance::query()->create([
        'class_session_id' => $session->id,
        'student_id' => $this->student->id,
        'status' => 'present',
    ]);

    Sanctum::actingAs($this->student);
});

// The enrolled path of this endpoint is broken in production, independently of
// any migration work: getCourseAttendance filters `attendances.course_offering_id`
// and orders by `attendances.session_date`, and neither column exists — both live
// on class_sessions. Every enrolled request therefore returns a 422 carrying a
// SQL error. Pinned here as the current behaviour, not as intended behaviour.
it('currently fails for an enrolled offering because the query names columns attendances does not have', function (): void {
    $this->getJson(route('v1.student.attendance.course-attendance', [
        'courseOfferingId' => $this->offering->id,
    ]))
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.code', 'BUSINESS_LOGIC_ERROR');
});

it('rejects course attendance for an offering the student is not enrolled in', function (): void {
    $other = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    $this->getJson(route('v1.student.attendance.course-attendance', [
        'courseOfferingId' => $other->id,
    ]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Student is not enrolled in this course');
});

it('returns the attendance report for the current period by default', function (): void {
    $this->getJson(route('v1.student.attendance.report'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Attendance report retrieved successfully');
});

it('returns the attendance report for an explicit semester', function (): void {
    $this->getJson(route('v1.student.attendance.report', ['semester_id' => $this->semester->id]))
        ->assertOk()
        ->assertJsonPath('success', true);
});
