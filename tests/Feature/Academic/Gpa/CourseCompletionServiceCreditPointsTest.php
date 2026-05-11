<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Services\CourseCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(CourseCompletionService::class);
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    $this->makeOffering = function (Unit $unit) {
        return CourseOffering::factory()->create([
            'semester_id' => $this->semester->id,
            'unit_id' => $unit->id,
            'campus_id' => $this->campus->id,
            'is_canvas_synced' => true, // skip aggregateManualGrades branch
        ]);
    };

    $this->makeRecord = function (CourseOffering $offering, array $overrides = []) {
        CourseRegistration::create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'registration_method' => 'admin_override',
            'credit_hours' => $offering->unit->credit_points ?? 3,
            'attempt_number' => 1,
            'is_retake' => false,
            'retake_fee' => 0.00,
            'is_retake_paid' => 'no',
        ]);

        return AcademicRecord::factory()->create(array_merge([
            'student_id' => $this->student->id,
            'semester_id' => $offering->semester_id,
            'unit_id' => $offering->unit_id,
            'program_id' => $this->student->program_id,
            'campus_id' => $this->campus->id,
            'course_offering_id' => $offering->id,
            'credit_hours' => 3.00,
            'credit_points' => 3.00,
            'grade_status' => 'in_progress',
            'is_passed' => false,
            'final_percentage' => null,
        ], $overrides));
    };

    // Reflection helper to invoke the private finalize method directly,
    // bypassing EGC progression / surveys / notifications.
    $this->finalize = function (CourseOffering $offering) {
        $ref = new ReflectionClass($this->service);
        $method = $ref->getMethod('finalizeAcademicRecords');
        $method->setAccessible(true);
        $method->invoke($this->service, $offering->fresh(['unit', 'syllabusTemplate']));
    };
});

it('snapshots credit_points and quality_points on a passing record', function () {
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $offering = ($this->makeOffering)($unit);
    $record = ($this->makeRecord)($offering, [
        'credit_hours' => 3.00,
        'credit_points' => 3.00,
        'final_percentage' => 80.0,
    ]);

    ($this->finalize)($offering);

    $record->refresh();
    expect((float) $record->credit_points)->toBe(3.0)
        ->and((float) $record->credit_points_earned)->toBe(3.0)
        ->and((float) $record->credit_hours_earned)->toBe(3.0)
        ->and((float) $record->quality_points)->toBe(240.0) // 80 * 3
        ->and($record->is_passed)->toBeTrue()
        ->and($record->grade_status)->toBe('final');
});

it('zeroes earned credits but keeps attempted credit_points on a failing record', function () {
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $offering = ($this->makeOffering)($unit);
    $record = ($this->makeRecord)($offering, [
        'credit_hours' => 3.00,
        'credit_points' => 3.00,
        'final_percentage' => 30.0,
    ]);

    ($this->finalize)($offering);

    $record->refresh();
    expect((float) $record->credit_points)->toBe(3.0)
        ->and((float) $record->credit_points_earned)->toBe(0.0)
        ->and((float) $record->credit_hours_earned)->toBe(0.0)
        ->and((float) $record->quality_points)->toBe(90.0) // 30 * 3 (attempted)
        ->and($record->is_passed)->toBeFalse();
});

it('falls back to credit_hours when credit_points is 0 (legacy AR safety net)', function () {
    // Simulate a legacy AR created before the backfill ran.
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $offering = ($this->makeOffering)($unit);
    $record = ($this->makeRecord)($offering, [
        'credit_hours' => 3.00,
        'credit_points' => 0.00, // missing
        'final_percentage' => 80.0,
    ]);

    ($this->finalize)($offering);

    $record->refresh();
    expect((float) $record->credit_points)->toBe(3.0) // snapshotted from credit_hours
        ->and((float) $record->credit_points_earned)->toBe(3.0)
        ->and((float) $record->quality_points)->toBe(240.0);
});

it('keeps quality_points zero when both credit_points and credit_hours are 0', function () {
    // A truly 0-credit unit (e.g. PE) should not contribute to GPA.
    $unit = Unit::factory()->create(['credit_points' => 0.0]);
    $offering = ($this->makeOffering)($unit);
    $record = ($this->makeRecord)($offering, [
        'credit_hours' => 0.00,
        'credit_hours_earned' => 0.00, // must be <= credit_hours per CHECK constraint
        'credit_points' => 0.00,
        'credit_points_earned' => 0.00,
        'final_percentage' => 80.0,
    ]);

    ($this->finalize)($offering);

    $record->refresh();
    expect((float) $record->credit_points)->toBe(0.0)
        ->and((float) $record->credit_points_earned)->toBe(0.0)
        ->and((float) $record->quality_points)->toBe(0.0)
        ->and($record->is_passed)->toBeTrue();
});
