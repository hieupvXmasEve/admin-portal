<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Services\V1\Student\CreditProgressService;
use App\Services\V1\Student\CurriculumService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Student-facing views must read pass/fail from is_passed, not completion_status
// ("finished" != "passed"). These exercise the protected decision methods behind
// the heavier public aggregators.

function callProtected(object $service, string $method, array $args): mixed
{
    $ref = new ReflectionMethod($service, $method);
    $ref->setAccessible(true);

    return $ref->invoke($service, ...$args);
}

it('CurriculumService shows a finished-but-failed unit as failed, not completed', function () {
    $service = app(CurriculumService::class);

    $failed = (new AcademicRecord())->forceFill(['completion_status' => 'completed', 'is_passed' => false, 'override_pass' => false]);
    $passed = (new AcademicRecord())->forceFill(['completion_status' => 'completed', 'is_passed' => true, 'override_pass' => false]);

    expect(callProtected($service, 'determineStudyStatus', [$passed, collect()])['status'])->toBe('completed')
        ->and(callProtected($service, 'determineStudyStatus', [$failed, collect()])['status'])->toBe('failed');
});

it('CurriculumService shows retaking when a failed unit has an active retake registration', function () {
    $service = app(CurriculumService::class);
    $failed = (new AcademicRecord())->forceFill(['completion_status' => 'completed', 'is_passed' => false, 'override_pass' => false]);
    $registrations = collect([(object) ['registration_status' => 'registered']]);

    expect(callProtected($service, 'determineStudyStatus', [$failed, $registrations])['status'])->toBe('retaking');
});

it('CreditProgressService counts failed and success rate from is_passed', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['code' => 'FALL2025-V']);
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    $makeRecord = function (bool $isPassed) use ($campus, $semester, $student): void {
        $unit = Unit::factory()->create(['credit_points' => 3.0]);
        $offering = CourseOffering::factory()->create([
            'semester_id' => $semester->id, 'unit_id' => $unit->id, 'campus_id' => $campus->id,
        ]);
        AcademicRecord::factory()->create([
            'student_id' => $student->id, 'semester_id' => $semester->id, 'unit_id' => $unit->id,
            'program_id' => $student->program_id, 'campus_id' => $campus->id, 'course_offering_id' => $offering->id,
            'credit_points' => 3.0, 'credit_hours' => 3.0, 'grade_status' => 'final', 'excluded_from_gpa' => false,
            // Both finished; one passed, one failed — the 212-row shape.
            'completion_status' => 'completed', 'is_passed' => $isPassed,
        ]);
    };
    $makeRecord(true);
    $makeRecord(false);

    $progress = callProtected(app(CreditProgressService::class), 'getSemesterProgress', [$student->fresh()]);

    expect($progress[0]['courses_completed'])->toBe(2)
        ->and($progress[0]['courses_failed'])->toBe(1)
        ->and($progress[0]['success_rate'])->toBe(50.0);
});
