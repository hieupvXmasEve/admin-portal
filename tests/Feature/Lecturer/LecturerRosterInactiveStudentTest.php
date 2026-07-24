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
use App\Models\User;
use App\Modules\Academic\Delivery\Support\LecturerAttendanceService;
use App\Modules\Identity\Models\LecturerAccessGrant;
use App\Services\V1\Lecturer\LecturerCourseService;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create([
        'code' => 'TST2026',
        'name' => 'Test Semester 2026',
    ]);
    $this->lecturerUser = User::factory()->create([
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    $this->lecturer = Lecture::factory()->create([
        'user_id' => $this->lecturerUser->id,
        'campus_id' => $this->campus->id,
        'employment_status' => 'active',
        'is_active' => true,
        'is_available_for_assignment' => true,
    ]);
    LecturerAccessGrant::query()->updateOrCreate([
        'user_id' => $this->lecturerUser->id,
    ], [
        'lecturer_id' => $this->lecturer->id,
        'token_subject_type' => Lecture::class,
        'status' => LecturerAccessGrant::STATUS_ACTIVE,
        'reason' => 'eligible_active_employment',
        'eligibility_evaluated_at' => now(),
        'granted_at' => now(),
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
        ->getSessionAttendance($this->lecturer->id, $this->session->id);

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

    $result = app(LecturerAttendanceService::class)->markAttendance($this->lecturer->id, $this->session->id, [
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
        ->generateAttendanceRecords($this->lecturer->id, $this->session->id);

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
    $completedRegistrationStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_pre_uni_gc');
    $deferredRegistrationStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_pre_uni_gc');

    collect([$egcStudent, $majorStudent, $deferredStudent, $dropoutStudent])
        ->each(fn (Student $student) => lecturerRosterRegistration($student, $this->offering));
    lecturerRosterRegistration($completedRegistrationStudent, $this->offering, [
        'registration_status' => 'completed',
    ]);
    lecturerRosterRegistration($deferredRegistrationStudent, $this->offering, [
        'registration_status' => 'defer',
    ]);

    $students = app(LecturerCourseService::class)
        ->getCourseStudents($this->lecturer, $this->offering->id);

    $studentsById = collect($students)->keyBy('student_id');

    expect($studentsById->keys()->all())
        ->toEqualCanonicalizing([
            $egcStudent->id,
            $majorStudent->id,
            $deferredStudent->id,
            $dropoutStudent->id,
            $completedRegistrationStudent->id,
            $deferredRegistrationStudent->id,
        ])
        ->and($studentsById[$egcStudent->id]['is_roster_active'])->toBeTrue()
        ->and($studentsById[$majorStudent->id]['is_roster_active'])->toBeTrue()
        ->and($studentsById[$deferredStudent->id]['is_roster_active'])->toBeFalse()
        ->and($studentsById[$deferredStudent->id]['roster_status'])->toBe('deferred')
        ->and($studentsById[$deferredStudent->id]['roster_status_label'])->toBe('Deferred')
        ->and($studentsById[$deferredStudent->id]['can_mark_attendance'])->toBeFalse()
        ->and($studentsById[$deferredStudent->id]['status'])->toBe('inactive')
        ->and($studentsById[$dropoutStudent->id]['is_roster_active'])->toBeFalse()
        ->and($studentsById[$dropoutStudent->id]['roster_status'])->toBe('dropout')
        ->and($studentsById[$completedRegistrationStudent->id]['is_roster_active'])->toBeFalse()
        ->and($studentsById[$completedRegistrationStudent->id]['roster_status'])->toBe('completed')
        ->and($studentsById[$completedRegistrationStudent->id]['can_mark_attendance'])->toBeFalse()
        ->and($studentsById[$completedRegistrationStudent->id]['status'])->toBe('inactive')
        ->and($studentsById[$deferredRegistrationStudent->id]['is_roster_active'])->toBeFalse()
        ->and($studentsById[$deferredRegistrationStudent->id]['roster_status'])->toBe('defer')
        ->and($studentsById[$deferredRegistrationStudent->id]['roster_status_label'])->toBe('Deferred')
        ->and($studentsById[$deferredRegistrationStudent->id]['can_mark_attendance'])->toBeFalse()
        ->and($studentsById[$deferredRegistrationStudent->id]['status'])->toBe('inactive');
});

it('returns completed and deferred registrations through the lecturer course students HTTP endpoint', function () {
    $activeStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_course');
    $completedRegistrationStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_pre_uni_gc');
    $deferredRegistrationStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_pre_uni_gc');

    lecturerRosterRegistration($activeStudent, $this->offering);
    lecturerRosterRegistration($completedRegistrationStudent, $this->offering, [
        'registration_status' => 'completed',
    ]);
    lecturerRosterRegistration($deferredRegistrationStudent, $this->offering, [
        'registration_status' => 'defer',
    ]);

    Sanctum::actingAs($this->lecturer);

    $response = $this->getJson("/api/v1/lecturer/courses/{$this->offering->id}/students");

    $response->assertOk()->assertJsonPath('success', true);

    $studentsById = collect($response->json('data'))->keyBy('student_id');

    expect($studentsById->keys()->all())
        ->toEqualCanonicalizing([
            $activeStudent->id,
            $completedRegistrationStudent->id,
            $deferredRegistrationStudent->id,
        ])
        ->and($studentsById[$activeStudent->id]['registration']['status'])->toBe('confirmed')
        ->and($studentsById[$activeStudent->id]['roster']['is_active'])->toBeTrue()
        ->and($studentsById[$activeStudent->id]['roster']['can_mark_attendance'])->toBeTrue()
        ->and($studentsById[$completedRegistrationStudent->id]['registration']['status'])->toBe('completed')
        ->and($studentsById[$completedRegistrationStudent->id]['roster']['status'])->toBe('completed')
        ->and($studentsById[$completedRegistrationStudent->id]['roster']['is_active'])->toBeFalse()
        ->and($studentsById[$completedRegistrationStudent->id]['roster']['can_mark_attendance'])->toBeFalse()
        ->and($studentsById[$deferredRegistrationStudent->id]['registration']['status'])->toBe('defer')
        ->and($studentsById[$deferredRegistrationStudent->id]['roster']['status'])->toBe('defer')
        ->and($studentsById[$deferredRegistrationStudent->id]['roster']['status_label'])->toBe('Deferred')
        ->and($studentsById[$deferredRegistrationStudent->id]['roster']['is_active'])->toBeFalse()
        ->and($studentsById[$deferredRegistrationStudent->id]['roster']['can_mark_attendance'])->toBeFalse();
});

it('returns visible roster counts from the lecturer courses index and detail endpoints', function () {
    $activeStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_course');
    $completedRegistrationStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_pre_uni_gc');
    $deferredRegistrationStudent = lecturerRosterStudent($this->campus, $this->semester, 'intake_pre_uni_gc');

    lecturerRosterRegistration($activeStudent, $this->offering);
    lecturerRosterRegistration($completedRegistrationStudent, $this->offering, [
        'registration_status' => 'completed',
    ]);
    lecturerRosterRegistration($deferredRegistrationStudent, $this->offering, [
        'registration_status' => 'defer',
    ]);

    Cache::flush();
    Sanctum::actingAs($this->lecturer);

    $indexResponse = $this->getJson('/api/v1/lecturer/courses?per_page=12&page=1');
    $detailResponse = $this->getJson("/api/v1/lecturer/courses/{$this->offering->id}");

    $indexResponse->assertOk()->assertJsonPath('success', true);
    $detailResponse->assertOk()->assertJsonPath('success', true);

    $course = collect($indexResponse->json('data'))->firstWhere('id', $this->offering->id);

    expect($course)->not->toBeNull()
        ->and($course['current_enrollment'])->toBe(3)
        ->and($course['enrollment_stats']['enrolled_count'])->toBe(3)
        ->and($course['enrollment_stats']['active_roster_count'])->toBe(1)
        ->and($course['enrollment_stats']['visible_roster_count'])->toBe(3)
        ->and($course['enrollment_stats']['completed_count'])->toBe(1)
        ->and($course['enrollment_stats']['deferred_count'])->toBe(1)
        ->and($detailResponse->json('data.course_offering.current_enrollment'))->toBe(3)
        ->and($detailResponse->json('data.course_offering.active_roster_students'))->toBe(1)
        ->and($detailResponse->json('data.course_offering.visible_roster_students'))->toBe(3)
        ->and($detailResponse->json('data.enrollment_statistics.enrolled_students'))->toBe(3)
        ->and($detailResponse->json('data.enrollment_statistics.active_roster_students'))->toBe(1)
        ->and($detailResponse->json('data.enrollment_statistics.visible_roster_students'))->toBe(3)
        ->and($detailResponse->json('data.enrollment_statistics.completed_students'))->toBe(1)
        ->and($detailResponse->json('data.enrollment_statistics.deferred_students'))->toBe(1);
});

it('returns lecturer course semester filter options with active flags', function () {
    Sanctum::actingAs($this->lecturer);

    $response = $this->getJson('/api/v1/lecturer/courses/filter-options');

    $response->assertOk()->assertJsonPath('success', true);

    $semesters = collect($response->json('data.semesters'));
    $semester = $semesters->firstWhere('id', $this->semester->id);

    expect($semester)->not->toBeNull()
        ->and($semester['name'])->toBe($this->semester->name)
        ->and($semester['code'])->toBe($this->semester->code)
        ->and($semester['is_active'])->toBeTrue();
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
