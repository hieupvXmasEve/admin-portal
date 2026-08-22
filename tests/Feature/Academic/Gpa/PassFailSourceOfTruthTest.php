<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Services\CourseStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Pass/fail is read from is_passed; completion_status means "finished", not "passed".
// The 162 real rows are is_passed=false + completion_status='completed' + grade_points>0,
// which the old isPassed() reported as passed.

it('reads pass from is_passed, not completion_status', function () {
    $record = (new AcademicRecord())->forceFill([
        'is_passed' => false,
        'completion_status' => 'completed',
        'grade_points' => 3.5,
        'override_pass' => false,
    ]);

    expect($record->isPassed())->toBeFalse()
        ->and($record->isFailed())->toBeTrue()
        ->and($record->isCompleted())->toBeTrue();
});

it('still honors override_pass as the manual escape hatch', function () {
    $record = (new AcademicRecord())->forceFill([
        'is_passed' => false,
        'completion_status' => 'completed',
        'override_pass' => true,
    ]);

    expect($record->isPassed())->toBeTrue()
        ->and($record->isFailed())->toBeFalse();
});

it('treats a passing record as passed and not failed', function () {
    $record = (new AcademicRecord())->forceFill([
        'is_passed' => true,
        'completion_status' => 'completed',
        'override_pass' => false,
    ]);

    expect($record->isPassed())->toBeTrue()
        ->and($record->isFailed())->toBeFalse();
});

it('treats an in-progress record as neither passed nor failed', function () {
    $record = (new AcademicRecord())->forceFill([
        'is_passed' => false,
        'completion_status' => 'in_progress',
        'override_pass' => false,
    ]);

    expect($record->isPassed())->toBeFalse()
        ->and($record->isFailed())->toBeFalse();
});

it('derives transcript completion_status as finished regardless of pass/fail', function () {
    $failed = new TranscriptEntry(['is_passed' => false]);
    $passed = new TranscriptEntry(['is_passed' => true]);

    expect($failed->completion_status)->toBe('completed')
        ->and($passed->completion_status)->toBe('completed');
});

it('counts course pass rate from is_passed, not completion_status', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id, 'unit_id' => $unit->id, 'campus_id' => $campus->id,
    ]);

    $makeRecord = function (bool $isPassed) use ($campus, $semester, $unit, $offering): void {
        $student = Student::factory()->forCampus($campus)->create([
            'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
        ]);
        AcademicRecord::factory()->create([
            'student_id' => $student->id, 'semester_id' => $semester->id, 'unit_id' => $unit->id,
            'program_id' => $student->program_id, 'campus_id' => $campus->id, 'course_offering_id' => $offering->id,
            'credit_points' => 3.0, 'grade_status' => 'final', 'excluded_from_gpa' => false,
            // A failed-but-completed record with grade_points > 0 — the shape the old
            // pass-rate counted as passed.
            'completion_status' => 'completed', 'grade_points' => 3.5, 'is_passed' => $isPassed,
        ]);
    };
    $makeRecord(true);
    $makeRecord(false);

    $detail = app(CourseStatisticsService::class)->getCourseDetail($offering->id);

    expect($detail['pass_rate'])->toBe(50.0);
});
