<?php

declare(strict_types=1);

use App\Actions\Academic\CalculateCumulativeGpaAction;
use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = app(CalculateCumulativeGpaAction::class);
    $this->campus = Campus::factory()->create();

    // Three sequential semesters with explicit start_date so the cumulative
    // `start_date <= target` window check is deterministic.
    $this->sem1 = Semester::factory()->create([
        'code' => 'FALL2025-TEST',
        'name' => 'Fall 2025',
        'start_date' => Carbon::create(2025, 9, 1),
        'end_date' => Carbon::create(2025, 12, 31),
    ]);
    $this->sem2 = Semester::factory()->create([
        'code' => 'SPRING2026-TEST',
        'name' => 'Spring 2026',
        'start_date' => Carbon::create(2026, 1, 5),
        'end_date' => Carbon::create(2026, 4, 30),
    ]);
    $this->sem3 = Semester::factory()->create([
        'code' => 'SUMMER2026-TEST',
        'name' => 'Summer 2026',
        'start_date' => Carbon::create(2026, 5, 4),
        'end_date' => Carbon::create(2026, 8, 31),
    ]);

    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->sem1->id,
    ]);

    $this->makeRecord = function (Semester $semester, array $overrides = []) {
        $unit = $overrides['unit'] ?? Unit::factory()->create(['credit_points' => 3.0]);
        unset($overrides['unit']);

        $offering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'campus_id' => $this->campus->id,
        ]);

        return AcademicRecord::factory()->create(array_merge([
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
    };
});

it('returns zeros when student has no records', function () {
    $result = $this->action->execute($this->student, null);

    expect($result)->toEqual([
        'gpa' => 0.0,
        'quality_points' => 0.0,
        'credit_points' => 0.0,
        'credit_points_earned' => 0.0,
    ]);
});

it('aggregates across all semesters when upToSemesterId is null', function () {
    ($this->makeRecord)($this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);
    ($this->makeRecord)($this->sem2, ['credit_points' => 2.0, 'final_percentage' => 60.0]);

    $result = $this->action->execute($this->student, null);

    // weighted: (80*3 + 60*2) / 5 = 360 / 5 = 72.0
    expect($result['gpa'])->toBe(72.0)
        ->and((float) $result['credit_points'])->toBe(5.0);
});

it('restricts the window to semesters up to the target start_date', function () {
    ($this->makeRecord)($this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);
    ($this->makeRecord)($this->sem2, ['credit_points' => 2.0, 'final_percentage' => 60.0]);
    // sem3 record must be excluded from "up to sem2" cumulative.
    ($this->makeRecord)($this->sem3, ['credit_points' => 4.0, 'final_percentage' => 30.0]);

    $resultUpToSem1 = $this->action->execute($this->student, $this->sem1->id);
    $resultUpToSem2 = $this->action->execute($this->student, $this->sem2->id);

    expect($resultUpToSem1['gpa'])->toBe(80.0)
        ->and((float) $resultUpToSem1['credit_points'])->toBe(3.0);

    expect($resultUpToSem2['gpa'])->toBe(72.0)
        ->and((float) $resultUpToSem2['credit_points'])->toBe(5.0);
});

it('counts every cross-semester retake attempt', function () {
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    ($this->makeRecord)($this->sem1, [
        'unit' => $unit,
        'credit_points' => 3.0,
        'final_percentage' => 60.0,
        'attempt_number' => 1,
        'is_passed' => false,
    ]);
    ($this->makeRecord)($this->sem2, [
        'unit' => $unit,
        'credit_points' => 3.0,
        'final_percentage' => 90.0,
        'attempt_number' => 2,
        'is_repeat_course' => true,
        'is_passed' => true,
    ]);

    $result = $this->action->execute($this->student, $this->sem2->id);

    // (60*3 + 90*3) / 6 = 75; both attempts contribute.
    expect($result['gpa'])->toBe(75.0)
        ->and((float) $result['credit_points'])->toBe(6.0)
        ->and((float) $result['credit_points_earned'])->toBe(3.0) // only the pass
        ->and($result['quality_points'])->toBe(450.0);
});

it('counts a lower-scoring retake alongside the original (no replacement)', function () {
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    ($this->makeRecord)($this->sem1, [
        'unit' => $unit,
        'credit_points' => 3.0,
        'final_percentage' => 75.0,
        'attempt_number' => 1,
        'is_passed' => true,
    ]);
    ($this->makeRecord)($this->sem2, [
        'unit' => $unit,
        'credit_points' => 3.0,
        'final_percentage' => 55.0,
        'attempt_number' => 2,
        'is_repeat_course' => true,
        'is_passed' => false,
    ]);

    $result = $this->action->execute($this->student, $this->sem2->id);

    // (75*3 + 55*3) / 6 = 65
    expect($result['gpa'])->toBe(65.0)
        ->and((float) $result['credit_points'])->toBe(6.0)
        ->and((float) $result['credit_points_earned'])->toBe(3.0);
});

it('excludes records flagged excluded_from_gpa across semesters', function () {
    ($this->makeRecord)($this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);
    ($this->makeRecord)($this->sem2, [
        'credit_points' => 3.0,
        'final_percentage' => 50.0,
        'excluded_from_gpa' => true,
        'gpa_exclusion_reason' => 'retake_superseded',
    ]);

    $result = $this->action->execute($this->student, null);

    expect($result['gpa'])->toBe(80.0)
        ->and((float) $result['credit_points'])->toBe(3.0);
});

it('separates earned credits from attempted credits when a unit is failed', function () {
    ($this->makeRecord)($this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0, 'is_passed' => true]);
    ($this->makeRecord)($this->sem2, [
        'credit_points' => 2.0,
        'final_percentage' => 30.0,
        'is_passed' => false,
    ]);

    $result = $this->action->execute($this->student, null);

    expect((float) $result['credit_points'])->toBe(5.0)
        ->and((float) $result['credit_points_earned'])->toBe(3.0);
});

it('ignores records with zero credit_points', function () {
    ($this->makeRecord)($this->sem1, ['credit_points' => 3.0, 'final_percentage' => 70.0]);
    ($this->makeRecord)($this->sem2, [
        'unit' => Unit::factory()->create(['credit_points' => 0.0]),
        'credit_points' => 0.0,
        'final_percentage' => 100.0,
    ]);

    $result = $this->action->execute($this->student, null);

    expect($result['gpa'])->toBe(70.0)
        ->and((float) $result['credit_points'])->toBe(3.0);
});
