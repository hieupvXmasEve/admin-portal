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
    $this->unit = Unit::factory()->create(['credit_points' => 3.0]);
    $this->offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
        'is_canvas_synced' => true,
    ]);

    // No syllabus template attached → default thresholds (grade 60, attendance 80).
    $this->makeRecord = function (string $code, array $attendance, float $score, array $overrides = []): AcademicRecord {
        $student = Student::factory()->forCampus($this->campus)->create([
            'student_id' => $code,
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $this->semester->id,
        ]);

        CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'registration_method' => 'admin_override',
            'credit_hours' => 3,
            'attempt_number' => 1,
            'is_retake' => false,
            'retake_fee' => 0.00,
            'is_retake_paid' => 'no',
        ]);

        return AcademicRecord::factory()->create(array_merge([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit->id,
            'program_id' => $student->program_id,
            'campus_id' => $this->campus->id,
            'course_offering_id' => $this->offering->id,
            'credit_hours' => 3.00,
            'credit_points' => 3.00,
            'grade_status' => 'in_progress',
            'is_passed' => false,
            'final_percentage' => $score,
            'total_present' => $attendance['present'] ?? 0,
            'total_late' => $attendance['late'] ?? 0,
            'total_absences' => $attendance['absent'] ?? 0,
            'total_not_recorded' => $attendance['not_recorded'] ?? 0,
            'total_class_sessions' => $attendance['sessions'] ?? 0,
        ], $overrides));
    };

    $this->finalize = function (): void {
        $ref = new ReflectionClass($this->service);
        $method = $ref->getMethod('finalizeAcademicRecords');
        $method->setAccessible(true);
        $method->invoke($this->service, $this->offering->fresh(['unit', 'syllabusTemplate']));
    };
});

it('records null failure_reason and a clean snapshot for a passing student', function () {
    $rec = ($this->makeRecord)('PASS-1', ['present' => 10, 'sessions' => 10], 80.0);

    ($this->finalize)();
    $rec->refresh();

    expect($rec->is_passed)->toBeTrue()
        ->and($rec->failure_reason)->toBeNull()
        ->and($rec->grade_status)->toBe('final')
        ->and($rec->failure_reason_snapshot['attendance_evidence_state'])->toBe('clean')
        ->and($rec->failure_reason_snapshot['attendance_pct'])->toEqual(100.0);
});

it('labels a grade-only failure grade_failed (resit lane)', function () {
    $rec = ($this->makeRecord)('GRADE-1', ['present' => 10, 'sessions' => 10], 45.0);

    ($this->finalize)();
    $rec->refresh();

    expect($rec->is_passed)->toBeFalse()
        ->and($rec->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED);
});

it('labels an attendance-only failure attendance_failed (retake lane)', function () {
    // grade 75 (pass) but 50% attendance → fails attendance only.
    $rec = ($this->makeRecord)('ATT-1', ['present' => 5, 'absent' => 5, 'sessions' => 10], 75.0);

    ($this->finalize)();
    $rec->refresh();

    expect($rec->is_passed)->toBeFalse()
        ->and($rec->failure_reason)->toBe(AcademicRecord::FAILURE_ATTENDANCE_FAILED)
        ->and($rec->failure_reason_snapshot['attendance_pct'])->toEqual(50.0);
});

it('labels a combined failure both_failed (retake lane)', function () {
    $rec = ($this->makeRecord)('BOTH-1', ['present' => 4, 'absent' => 6, 'sessions' => 10], 40.0);

    ($this->finalize)();
    $rec->refresh();

    expect($rec->is_passed)->toBeFalse()
        ->and($rec->failure_reason)->toBe(AcademicRecord::FAILURE_BOTH_FAILED);
});

it('passes on recorded attendance but soft-flags not_recorded evidence', function () {
    // 5 present + 5 not-recorded → recorded denom 5 → 100% (passes), evidence incomplete.
    $rec = ($this->makeRecord)('NR-1', ['present' => 5, 'not_recorded' => 5, 'sessions' => 10], 75.0);

    ($this->finalize)();
    $rec->refresh();

    expect($rec->is_passed)->toBeTrue()
        ->and($rec->failure_reason)->toBeNull()
        ->and($rec->failure_reason_snapshot['attendance_evidence_state'])->toBe('not_recorded');
});

it('keeps grade-only behavior when no attendance is recorded', function () {
    $pass = ($this->makeRecord)('NOATT-PASS', ['sessions' => 0], 85.0);
    $fail = ($this->makeRecord)('NOATT-FAIL', ['sessions' => 0], 30.0);

    ($this->finalize)();

    expect($pass->refresh()->is_passed)->toBeTrue()
        ->and($pass->failure_reason)->toBeNull()
        ->and($fail->refresh()->is_passed)->toBeFalse()
        ->and($fail->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($fail->failure_reason_snapshot['attendance_evidence_state'])->toBe('no_sessions');
});

it('labels an overridden failure manual_failed and respects an overridden pass', function () {
    $overriddenPass = ($this->makeRecord)('OVR-PASS', ['present' => 2, 'absent' => 8, 'sessions' => 10], 20.0, [
        'override_pass' => true,
        'is_passed' => true,
    ]);

    ($this->finalize)();
    $overriddenPass->refresh();

    expect($overriddenPass->is_passed)->toBeTrue()
        ->and($overriddenPass->failure_reason)->toBeNull();
});
