<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingAttendanceReportQuery;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingOperationalAttendanceStatisticsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports roster-scoped operational presence explicitly while preserving the legacy rate alias', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'student_id' => 'STU-001',
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0,
        'is_retake_paid' => 'no',
    ]);

    $presentSession = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'status' => 'completed',
        'session_date' => '2026-01-15',
        'sequence_number' => 1,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);
    $absentSession = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'status' => 'completed',
        'session_date' => '2026-01-16',
        'sequence_number' => 2,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
    ]);
    Attendance::create([
        'class_session_id' => $presentSession->id,
        'student_id' => $student->id,
        'status' => 'present',
        'recording_method' => 'manual',
    ]);
    Attendance::create([
        'class_session_id' => $absentSession->id,
        'student_id' => $student->id,
        'status' => 'absent',
        'recording_method' => 'manual',
    ]);

    $report = app(GetCourseOfferingAttendanceReportQuery::class)->handle($offering);
    $statistics = app(GetCourseOfferingOperationalAttendanceStatisticsQuery::class)->handle($offering);

    expect($report['statistics']['attendance_metric'])->toBe('operational_presence_rate')
        ->and($report['attendance_grid'])->toHaveCount(1)
        ->and($report['attendance_grid'][0])
        ->toMatchArray([
            'student_id' => 'STU-001',
            'total_present' => 1,
            'total_absences' => 1,
            'operational_presence_rate' => 50.0,
            'attendance_percentage' => 50.0,
        ]);

    expect($statistics)->toMatchArray([
        'completed_sessions' => 2,
        'sessions_with_attendance' => 2,
        'operational_presence_rate' => 50.0,
        'overall_attendance_rate' => 50.0,
    ]);
});
