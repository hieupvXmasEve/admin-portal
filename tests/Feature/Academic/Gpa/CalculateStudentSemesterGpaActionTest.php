<?php

declare(strict_types=1);

use App\Actions\Academic\CalculateStudentSemesterGpaAction;
use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = app(CalculateStudentSemesterGpaAction::class);
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    $this->makeRecord = function (array $overrides = []) {
        $semester = $overrides['semester'] ?? $this->semester;
        $unit = $overrides['unit'] ?? Unit::factory()->create(['credit_points' => 3.0]);
        unset($overrides['semester'], $overrides['unit']);

        $offering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'campus_id' => $this->campus->id,
        ]);

        $record = AcademicRecord::factory()->create(array_merge([
            'student_id' => $this->student->id,
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'program_id' => $this->student->program_id,
            'campus_id' => $this->campus->id,
            'course_offering_id' => $offering->id,
            'credit_points' => (float) $unit->credit_points,
            'grade_status' => 'final',
            'excluded_from_gpa' => false,
            'is_passed' => true,
        ], $overrides));

        if ($record->grade_status === 'final') {
            TranscriptEntry::query()->create([
                'course_result_id' => $record->id,
                'student_id' => $record->student_id,
                'course_offering_id' => $record->course_offering_id,
                'semester_id' => $record->semester_id,
                'unit_id' => $record->unit_id,
                'program_id' => $record->program_id,
                'campus_id' => $record->campus_id,
                'attempt_number' => $record->attempt_number ?? 1,
                'final_percentage' => $record->final_percentage,
                'final_letter_grade' => $record->final_letter_grade,
                'credit_points' => $record->credit_points,
                'credit_points_earned' => $record->is_passed ? $record->credit_points : 0,
                'quality_points' => (float) $record->final_percentage * (float) $record->credit_points,
                'is_passed' => $record->is_passed,
                'excluded_from_gpa' => $record->excluded_from_gpa,
                'affects_academic_standing' => true,
                'affects_graduation_requirement' => true,
                'satisfies_prerequisite' => $record->is_passed,
                'finalized_at' => now(),
            ]);
        }

        return $record;
    };
});

it('returns zeros when no records exist', function () {
    $result = $this->action->execute($this->student, $this->semester->id);

    expect($result)->toEqual([
        'gpa' => 0.0,
        'quality_points' => 0.0,
        'credit_points' => 0.0,
        'credit_points_earned' => 0.0,
    ]);
});

it('computes credit-weighted gpa across multiple units in the semester', function () {
    $u1 = Unit::factory()->create(['credit_points' => 3.0]);
    $u2 = Unit::factory()->create(['credit_points' => 2.0]);
    ($this->makeRecord)(['unit' => $u1, 'credit_points' => 3.0, 'final_percentage' => 80.0]);
    ($this->makeRecord)(['unit' => $u2, 'credit_points' => 2.0, 'final_percentage' => 60.0]);

    $result = $this->action->execute($this->student, $this->semester->id);

    // weighted: (80*3 + 60*2) / (3+2) = (240 + 120) / 5 = 72.0
    expect($result['gpa'])->toBe(72.0)
        ->and($result['quality_points'])->toBe(360.0)
        ->and((float) $result['credit_points'])->toBe(5.0)
        ->and((float) $result['credit_points_earned'])->toBe(5.0);
});

it('excludes records with non-final grade status', function () {
    ($this->makeRecord)(['final_percentage' => 80.0, 'credit_points' => 3.0]);
    ($this->makeRecord)([
        'unit' => Unit::factory()->create(['credit_points' => 3.0]),
        'credit_points' => 3.0,
        'final_percentage' => 100.0,
        'grade_status' => 'in_progress',
    ]);

    $result = $this->action->execute($this->student, $this->semester->id);

    expect($result['gpa'])->toBe(80.0);
});

it('excludes records flagged excluded_from_gpa', function () {
    ($this->makeRecord)(['final_percentage' => 70.0, 'credit_points' => 3.0]);
    ($this->makeRecord)([
        'unit' => Unit::factory()->create(['credit_points' => 3.0]),
        'credit_points' => 3.0,
        'final_percentage' => 100.0,
        'excluded_from_gpa' => true,
        'gpa_exclusion_reason' => 'retake_superseded',
    ]);

    $result = $this->action->execute($this->student, $this->semester->id);

    expect($result['gpa'])->toBe(70.0);
});

it('ignores records with zero credit_points', function () {
    ($this->makeRecord)(['final_percentage' => 80.0, 'credit_points' => 3.0]);
    ($this->makeRecord)([
        'unit' => Unit::factory()->create(['credit_points' => 0.0]),
        'credit_points' => 0.0,
        'final_percentage' => 95.0,
    ]);

    $result = $this->action->execute($this->student, $this->semester->id);

    // 0-credit unit must not move the gpa.
    expect($result['gpa'])->toBe(80.0)
        ->and((float) $result['credit_points'])->toBe(3.0);
});

it('counts every attempt when a unit is taken twice in the same semester', function () {
    // "Có học là có tính điểm" — every final attempt counts.
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    ($this->makeRecord)(['unit' => $unit, 'credit_points' => 3.0, 'final_percentage' => 60.0, 'attempt_number' => 1, 'is_passed' => false]);
    ($this->makeRecord)(['unit' => $unit, 'credit_points' => 3.0, 'final_percentage' => 90.0, 'attempt_number' => 2, 'is_passed' => true]);

    $result = $this->action->execute($this->student, $this->semester->id);

    // (60*3 + 90*3) / 6 = 75; credits double-count for attempted; earned = 3 (one pass).
    expect($result['gpa'])->toBe(75.0)
        ->and((float) $result['credit_points'])->toBe(6.0)
        ->and((float) $result['credit_points_earned'])->toBe(3.0);
});

it('separates credit_points_earned from credit_points when student fails a unit', function () {
    ($this->makeRecord)(['final_percentage' => 80.0, 'credit_points' => 3.0, 'is_passed' => true]);
    ($this->makeRecord)([
        'unit' => Unit::factory()->create(['credit_points' => 2.0]),
        'credit_points' => 2.0,
        'final_percentage' => 30.0,
        'is_passed' => false,
    ]);

    $result = $this->action->execute($this->student, $this->semester->id);

    expect((float) $result['credit_points'])->toBe(5.0)
        ->and((float) $result['credit_points_earned'])->toBe(3.0);
});
