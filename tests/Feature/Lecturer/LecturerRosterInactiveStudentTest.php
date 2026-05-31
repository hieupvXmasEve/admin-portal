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
use App\Services\V1\Lecturer\LecturerAttendanceService;
use App\Services\V1\Lecturer\LecturerCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create([
        'code' => 'TST2026',
        'name' => 'Test Semester 2026',
    ]);
    $this->lecturer = Lecture::factory()->create([
        'campus_id' => $this->campus->id,
    ]);
    $this->unit = Unit::factory()->create([
        'code' => 'COS10009',
        'name' => 'Introduction to Programming',
        'credit_points' => 3.0,
    ]);
    $this->offering = CourseOffering::query()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'lecture_id' => $this->lecturer->id,
        'campus_id' => $this->campus->id,
        'section_code' => 'A',
        'max_capacity' => 30,
        'current_enrollment' => 4,
        'delivery_mode' => 'in_person',
        'schedule_days' => ['Monday'],
        'schedule_time_start' => '09:00:00',
        'schedule_time_end' => '11:00:00',
        'is_active' => true,
        'enrollment_status' => 'open',
    ]);
    $this->session = ClassSession::query()->create([
        'course_offering_id' => $this->offering->id,
        'lecture_id' => $this->lecturer->id,
        'session_title' => 'Week 1 Lecture',
        'session_date' => '2026-05-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'duration_minutes' => 120,
        'session_type' => 'lecture',
        'delivery_mode' => 'in_person',
        'status' => 'completed',
        'attendance_required' => true,
        'attendance_tracking_enabled' => true,
        'sequence_number' => 1,
    ]);
});

it('includes deferred and dropout students in lecturer session attendance with inactive roster metadata', function () {
    $egcStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_pre_uni_gc');
    $majorStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_major');
    $deferredStudent = lecturerRosterStudent($this->campus, $this->semester, 'deferred');
    $dropoutStudent = lecturerRosterStudent($this->campus, $this->semester, 'dropout');

    collect([$egcStudent, $majorStudent, $deferredStudent, $dropoutStudent])
        ->each(fn (Student $student) => lecturerRosterRegistration($student, $this->offering));

    collect([$egcStudent, $majorStudent, $deferredStudent, $dropoutStudent])
        ->each(fn (Student $student) => Attendance::query()->create([
            'class_session_id' => $this->session->id,
            'student_id' => $student->id,
            'recorded_by_lecture_id' => $this->lecturer->id,
            'status' => 'present',
            'recording_method' => 'manual',
            'affects_grade' => true,
        ]));

    $attendance = app(LecturerAttendanceService::class)
        ->getSessionAttendance($this->lecturer, $this->session->id);

    $students = collect($attendance['students'])->keyBy('student_id');

    expect($students->keys()->all())
        ->toEqualCanonicalizing([$egcStudent->id, $majorStudent->id, $deferredStudent->id, $dropoutStudent->id])
        ->and($students[$egcStudent->id]['is_roster_active'])->toBeTrue()
        ->and($students[$majorStudent->id]['is_roster_active'])->toBeTrue()
        ->and($students[$deferredStudent->id]['is_roster_active'])->toBeFalse()
        ->and($students[$deferredStudent->id]['roster_status'])->toBe('deferred')
        ->and($students[$deferredStudent->id]['roster_status_label'])->toBe('Deferred')
        ->and($students[$deferredStudent->id]['can_mark_attendance'])->toBeFalse()
        ->and($students[$dropoutStudent->id]['is_roster_active'])->toBeFalse()
        ->and($students[$dropoutStudent->id]['roster_status'])->toBe('dropout')
        ->and($attendance['summary']['total_enrolled'])->toBe(2)
        ->and($attendance['summary']['total_roster'])->toBe(4)
        ->and($attendance['summary']['active_roster'])->toBe(2)
        ->and($attendance['summary']['inactive_roster'])->toBe(2)
        ->and($attendance['summary']['attendance_counts']['present'])->toBe(2)
        ->and($attendance['session']['expected_attendees'])->toBe(2)
        ->and($attendance['session']['actual_attendees'])->toBe(2);
});

it('rejects marking attendance for a DE student left in a stale client payload', function () {
    $activeStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_course');
    $deferredStudent = lecturerRosterStudent($this->campus, $this->semester, 'deferred');

    lecturerRosterRegistration($activeStudent, $this->offering);
    lecturerRosterRegistration($deferredStudent, $this->offering);

    $result = app(LecturerAttendanceService::class)->markAttendance($this->lecturer, $this->session->id, [
        ['student_id' => $activeStudent->id, 'status' => 'present'],
        ['student_id' => $deferredStudent->id, 'status' => 'present'],
    ]);

    expect($result['total_marked'])->toBe(1)
        ->and($result['total_errors'])->toBe(1)
        ->and($result['errors'][0]['student_id'])->toBe($deferredStudent->id)
        ->and($result['errors'][0]['error'])->toBe('Student is not active in this class roster');

    $this->assertDatabaseHas('attendances', [
        'class_session_id' => $this->session->id,
        'student_id' => $activeStudent->id,
        'status' => 'present',
    ]);
    $this->assertDatabaseMissing('attendances', [
        'class_session_id' => $this->session->id,
        'student_id' => $deferredStudent->id,
    ]);

    $this->session->refresh();

    expect($this->session->expected_attendees)->toBe(1)
        ->and($this->session->actual_attendees)->toBe(1)
        ->and((float) $this->session->attendance_percentage)->toBe(100.0);
});

it('generates default attendance records only for active EGC and Major roster students', function () {
    $egcStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_pre_uni_gc');
    $majorStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_major');
    $deferredStudent = lecturerRosterStudent($this->campus, $this->semester, 'deferred');
    $dropoutTransferStudent = lecturerRosterStudent($this->campus, $this->semester, 'dropout_transfer');

    collect([$egcStudent, $majorStudent, $deferredStudent, $dropoutTransferStudent])
        ->each(fn (Student $student) => lecturerRosterRegistration($student, $this->offering));

    $result = app(LecturerAttendanceService::class)
        ->generateAttendanceRecords($this->lecturer, $this->session->id);

    expect($result['total_records_created'])->toBe(2);

    expect(Attendance::query()
        ->where('class_session_id', $this->session->id)
        ->pluck('student_id')
        ->all())->toEqualCanonicalizing([$egcStudent->id, $majorStudent->id]);
});

it('returns active and inactive roster students from the lecturer course students API surface', function () {
    $egcStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_pre_uni_gc');
    $majorStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_major');
    $deferredStudent = lecturerRosterStudent($this->campus, $this->semester, 'deferred');
    $dropoutStudent = lecturerRosterStudent($this->campus, $this->semester, 'dropout');

    collect([$egcStudent, $majorStudent, $deferredStudent, $dropoutStudent])
        ->each(fn (Student $student) => lecturerRosterRegistration($student, $this->offering));

    $students = app(LecturerCourseService::class)
        ->getCourseStudents($this->lecturer, $this->offering->id);

    $studentsById = collect($students)->keyBy('student_id');

    expect($studentsById->keys()->all())
        ->toEqualCanonicalizing([$egcStudent->id, $majorStudent->id, $deferredStudent->id, $dropoutStudent->id])
        ->and($studentsById[$egcStudent->id]['is_roster_active'])->toBeTrue()
        ->and($studentsById[$majorStudent->id]['is_roster_active'])->toBeTrue()
        ->and($studentsById[$deferredStudent->id]['is_roster_active'])->toBeFalse()
        ->and($studentsById[$deferredStudent->id]['roster_status'])->toBe('deferred')
        ->and($studentsById[$deferredStudent->id]['roster_status_label'])->toBe('Deferred')
        ->and($studentsById[$deferredStudent->id]['can_mark_attendance'])->toBeFalse()
        ->and($studentsById[$deferredStudent->id]['status'])->toBe('inactive')
        ->and($studentsById[$dropoutStudent->id]['is_roster_active'])->toBeFalse()
        ->and($studentsById[$dropoutStudent->id]['roster_status'])->toBe('dropout');
});

function lecturerRosterStudent(Campus $campus, Semester $semester, string $status): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->create([
            'status' => $status,
            'academic_status' => 'active',
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_gc' => $status === 'intake_pre_uni_gc' ? $semester->id : null,
            'intake_major' => in_array($status, ['intake_course', 'intake_major'], true) ? $semester->id : null,
        ]);
}

function lecturerRosterRegistration(Student $student, CourseOffering $offering, array $overrides = []): CourseRegistration
{
    return CourseRegistration::query()->create(array_merge([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $offering->semester_id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0,
        'is_retake_paid' => 'no',
    ], $overrides));
}
