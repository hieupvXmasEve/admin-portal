<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Regression coverage for the lecturer attendance API (routes/api/v1/lecturer.php,
 * Api/V1/Lecturer/AttendanceController) — a fully separate surface from the
 * Course Offering Cockpit's attendance recording (ADR 0013 phase B) and not
 * touched by that work. This proves the lecturer mark-attendance flow still
 * works end-to-end through its own route, middleware, and controller.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->lecturer = Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'is_active' => true,
        'employment_status' => 'active',
        'is_available_for_assignment' => true,
    ]);
    $this->unit = Unit::factory()->create();
    $this->offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'lecture_id' => $this->lecturer->id,
        'campus_id' => $this->campus->id,
    ]);
    $this->session = ClassSession::factory()->create([
        'course_offering_id' => $this->offering->id,
        'lecture_id' => $this->lecturer->id,
        'status' => 'completed',
    ]);
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    // markStudentAttendance requires an active class-roster registration.
    CourseRegistration::query()->create([
        'student_id' => $this->student->id,
        'course_offering_id' => $this->offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0,
        'is_retake_paid' => 'no',
    ]);
});

it('marks attendance through the lecturer API unaffected by the cockpit attendance recording work', function () {
    Sanctum::actingAs($this->lecturer);

    $response = $this->postJson("/api/v1/lecturer/attendance/sessions/{$this->session->id}/mark", [
        'attendance_data' => [
            ['student_id' => $this->student->id, 'status' => 'present'],
        ],
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('attendances', [
        'class_session_id' => $this->session->id,
        'student_id' => $this->student->id,
        'status' => 'present',
        'recording_method' => 'manual',
    ]);
});

it('rejects lecturer attendance marking for a session the lecturer does not own', function () {
    $otherLecturer = Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'is_active' => true,
        'employment_status' => 'active',
        'is_available_for_assignment' => true,
    ]);

    Sanctum::actingAs($otherLecturer);

    $response = $this->postJson("/api/v1/lecturer/attendance/sessions/{$this->session->id}/mark", [
        'attendance_data' => [
            ['student_id' => $this->student->id, 'status' => 'present'],
        ],
    ]);

    $response->assertNotFound();

    $this->assertDatabaseMissing('attendances', [
        'class_session_id' => $this->session->id,
        'student_id' => $this->student->id,
    ]);
});
