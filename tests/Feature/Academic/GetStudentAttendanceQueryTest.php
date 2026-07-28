<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Queries\GetStudentAcademicAttendanceDetailsQuery;
use App\Modules\Academic\Delivery\Queries\GetStudentAcademicAttendanceSummaryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('separates retake attendance attempts for the same unit', function () {
    $semester = Semester::factory()->create([
        'code' => 'SUMMER2026',
        'name' => 'Summer 2026',
    ]);
    $student = Student::factory()
        ->state([
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
    $unit = Unit::factory()->create([
        'code' => 'AU015',
        'name' => 'Physical Education: Vovinam Level 2',
    ]);

    $originalOffering = CourseOffering::query()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'section_code' => 'A',
        'max_capacity' => 30,
        'current_enrollment' => 1,
        'delivery_mode' => 'in_person',
        'is_active' => true,
        'enrollment_status' => 'open',
    ]);

    $retakeOffering = CourseOffering::query()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'section_code' => 'R1',
        'max_capacity' => 30,
        'current_enrollment' => 1,
        'delivery_mode' => 'in_person',
        'is_active' => true,
        'enrollment_status' => 'open',
    ]);

    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $originalOffering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'completed',
        'registration_date' => now()->subMonths(2),
        'registration_method' => 'advisor',
        'credit_hours' => 2,
        'credit_points' => 2,
        'attempt_number' => 1,
        'is_retake' => false,
    ]);

    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $retakeOffering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'registered',
        'registration_date' => now()->subMonth(),
        'registration_method' => 'advisor',
        'credit_hours' => 2,
        'credit_points' => 2,
        'attempt_number' => 2,
        'is_retake' => true,
    ]);

    createAttendanceForAttempt($student, $originalOffering, [
        ['2026-05-01', 'present'],
        ['2026-05-02', 'absent'],
    ]);

    createAttendanceForAttempt($student, $retakeOffering, [
        ['2026-05-22', 'absent'],
        ['2026-05-23', 'present'],
        ['2026-05-30', 'present'],
    ]);

    $summary = app(GetStudentAcademicAttendanceSummaryQuery::class)->execute($student->id);

    expect($summary['data'])->toHaveCount(2)
        ->and($summary['summary']['total_units'])->toBe(1)
        ->and($summary['summary']['total_attempts'])->toBe(2)
        ->and($summary['summary']['total_sessions'])->toBe(5)
        ->and($summary['summary']['total_attended'])->toBe(3)
        ->and($summary['summary']['overall_percentage'])->toBe(60.0);

    $originalAttempt = $summary['data']->firstWhere('course_offering_id', $originalOffering->id);
    $retakeAttempt = $summary['data']->firstWhere('course_offering_id', $retakeOffering->id);

    expect($originalAttempt)
        ->not->toBeNull()
        ->and($originalAttempt['total_sessions'])->toBe(2)
        ->and($originalAttempt['attendance_percentage'])->toBe(50.0)
        ->and($originalAttempt['is_retake'])->toBeFalse()
        ->and($originalAttempt['attempt_label'])->toBe('Attempt 1')
        ->and($retakeAttempt)
        ->not->toBeNull()
        ->and($retakeAttempt['total_sessions'])->toBe(3)
        ->and($retakeAttempt['attendance_percentage'])->toBe(66.67)
        ->and($retakeAttempt['is_retake'])->toBeTrue()
        ->and($retakeAttempt['attempt_label'])->toBe('Retake attempt 2');

    $retakeDetails = app(GetStudentAcademicAttendanceDetailsQuery::class)->execute(
        $student->id,
        $unit->id,
        $semester->id,
        $retakeOffering->id
    );

    expect($retakeDetails['total_sessions'])->toBe(3)
        ->and($retakeDetails['attendance_percentage'])->toBe(66.67)
        ->and($retakeDetails['course_offering_id'])->toBe($retakeOffering->id);
});

function createAttendanceForAttempt(Student $student, CourseOffering $courseOffering, array $sessions): void
{
    foreach ($sessions as $index => [$sessionDate, $status]) {
        $classSession = ClassSession::query()->create([
            'course_offering_id' => $courseOffering->id,
            'session_date' => $sessionDate,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'sequence_number' => $index + 1,
            'status' => 'completed',
            'attendance_required' => true,
            'attendance_tracking_enabled' => true,
        ]);

        Attendance::query()->create([
            'class_session_id' => $classSession->id,
            'student_id' => $student->id,
            'status' => $status,
        ]);
    }
}
