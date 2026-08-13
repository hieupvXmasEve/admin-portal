<?php

declare(strict_types=1);

use App\Actions\Academic\FinalizeSemesterGpaAction;
use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = app(FinalizeSemesterGpaAction::class);
    $this->campus = Campus::factory()->create();
    // finalized_by_id now FKs to users(id) (migration: change_gpa_calculations_finalized_by_id_to_users).
    $this->admin = User::factory()->create();

    $this->sem1 = Semester::factory()->create([
        'code' => 'FALL2025-F',
        'start_date' => Carbon::create(2025, 9, 1),
        'end_date' => Carbon::create(2025, 12, 31),
    ]);
    $this->sem2 = Semester::factory()->create([
        'code' => 'SPRING2026-F',
        'start_date' => Carbon::create(2026, 1, 5),
        'end_date' => Carbon::create(2026, 4, 30),
    ]);

    $this->makeStudent = function (?Campus $campus = null) {
        return Student::factory()->forCampus($campus ?? $this->campus)->create([
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $this->sem1->id,
        ]);
    };

    $this->makeRecord = function (Student $student, Semester $semester, array $overrides = []) {
        $unit = $overrides['unit'] ?? Unit::factory()->create(['credit_points' => 3.0]);
        unset($overrides['unit']);

        $offering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'campus_id' => $student->campus_id,
        ]);

        $record = AcademicRecord::factory()->create(array_merge([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'program_id' => $student->program_id,
            'campus_id' => $student->campus_id,
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

it('finalizes gpa for a student with one credit-bearing semester', function () {
    $student = ($this->makeStudent)();
    ($this->makeRecord)($student, $this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);
    ($this->makeRecord)($student, $this->sem1, ['credit_points' => 2.0, 'final_percentage' => 60.0]);

    $result = $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    expect($result['success'])->toBeTrue()
        ->and($result['processed_students'])->toBe(1)
        ->and($result['skipped']['already_finalized'])->toBe(0);

    $gpa = GpaCalculation::where('student_id', $student->id)
        ->where('semester_id', $this->sem1->id)
        ->first();

    expect($gpa)->not->toBeNull()
        ->and((float) $gpa->semester_gpa)->toBe(72.0)
        ->and((float) $gpa->cumulative_gpa)->toBe(72.0)
        ->and((float) $gpa->semester_credit_points)->toBe(5.0)
        ->and($gpa->is_finalized)->toBeTrue()
        ->and($gpa->is_current)->toBeTrue()
        ->and($gpa->academic_standing)->toBe('normal');
});

it('marks a low-gpa student as warning standing', function () {
    $student = ($this->makeStudent)();
    ($this->makeRecord)($student, $this->sem1, [
        'credit_points' => 3.0,
        'final_percentage' => 40.0,
        'is_passed' => false,
    ]);

    $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    $gpa = GpaCalculation::where('student_id', $student->id)->first();
    expect($gpa->academic_standing)->toBe('warning');
});

it('skips students with pending (non-final) grades in the semester', function () {
    $student = ($this->makeStudent)();
    ($this->makeRecord)($student, $this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);
    ($this->makeRecord)($student, $this->sem1, [
        'credit_points' => 3.0,
        'final_percentage' => 0.0,
        'grade_status' => 'in_progress',
    ]);

    $result = $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    expect($result['processed_students'])->toBe(0)
        ->and($result['skipped']['pending_grades'])->toBe(1);

    expect(GpaCalculation::where('student_id', $student->id)->exists())->toBeFalse();
});

it('refuses to refinalize a semester already finalized for the same student', function () {
    $student = ($this->makeStudent)();
    ($this->makeRecord)($student, $this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);

    $first = $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);
    expect($first['success'])->toBeTrue()
        ->and($first['processed_students'])->toBe(1);

    $second = $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    expect($second['success'])->toBeFalse()
        ->and($second['processed_students'])->toBe(0)
        ->and($second['skipped']['already_finalized'])->toBe(1)
        ->and($second['message'])->toContain('already finalized');

    expect(GpaCalculation::where('student_id', $student->id)->count())->toBe(1);
});

it('respects campus scoping when finalizing', function () {
    $otherCampus = Campus::factory()->create();

    $here = ($this->makeStudent)();
    $there = ($this->makeStudent)($otherCampus);
    ($this->makeRecord)($here, $this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);
    ($this->makeRecord)($there, $this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);

    $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    expect(GpaCalculation::where('student_id', $here->id)->exists())->toBeTrue()
        ->and(GpaCalculation::where('student_id', $there->id)->exists())->toBeFalse();
});

it('moves is_current flag forward when a later semester is finalized', function () {
    $student = ($this->makeStudent)();
    ($this->makeRecord)($student, $this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);
    ($this->makeRecord)($student, $this->sem2, ['credit_points' => 2.0, 'final_percentage' => 60.0]);

    $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);
    $this->action->execute($this->sem2->id, $this->admin->id, $this->campus->id);

    $sem1Calc = GpaCalculation::where('student_id', $student->id)->where('semester_id', $this->sem1->id)->first();
    $sem2Calc = GpaCalculation::where('student_id', $student->id)->where('semester_id', $this->sem2->id)->first();

    expect($sem1Calc->is_current)->toBeFalse()
        ->and($sem2Calc->is_current)->toBeTrue()
        ->and((float) $sem2Calc->semester_gpa)->toBe(60.0)
        ->and((float) $sem2Calc->cumulative_gpa)->toBe(72.0)
        ->and((float) $sem2Calc->cumulative_credit_points)->toBe(5.0);
});

it('counts every retake attempt across semesters when finalizing', function () {
    $student = ($this->makeStudent)();
    $sharedUnit = Unit::factory()->create(['credit_points' => 3.0]);

    // Sem 1: fail (60). Sem 2: pass (90). Both attempts contribute to cumulative.
    ($this->makeRecord)($student, $this->sem1, [
        'unit' => $sharedUnit,
        'credit_points' => 3.0,
        'final_percentage' => 60.0,
        'attempt_number' => 1,
        'is_passed' => false,
    ]);
    ($this->makeRecord)($student, $this->sem2, [
        'unit' => $sharedUnit,
        'credit_points' => 3.0,
        'final_percentage' => 90.0,
        'attempt_number' => 2,
        'is_repeat_course' => true,
        'is_passed' => true,
    ]);

    $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);
    $this->action->execute($this->sem2->id, $this->admin->id, $this->campus->id);

    $sem2Calc = GpaCalculation::where('student_id', $student->id)->where('semester_id', $this->sem2->id)->first();

    // semester 2 reflects only the pass (90, cp=3).
    // cumulative averages both attempts: (60*3 + 90*3) / 6 = 75.
    // earned credits = 3 (only sem 2 attempt passed).
    expect((float) $sem2Calc->semester_gpa)->toBe(90.0)
        ->and((float) $sem2Calc->cumulative_gpa)->toBe(75.0)
        ->and((float) $sem2Calc->cumulative_credit_points)->toBe(6.0)
        ->and((float) $sem2Calc->cumulative_credit_points_earned)->toBe(3.0);
});

it('skips students whose only records are zero-credit units', function () {
    $student = ($this->makeStudent)();
    ($this->makeRecord)($student, $this->sem1, [
        'unit' => Unit::factory()->create(['credit_points' => 0.0]),
        'credit_points' => 0.0,
        'final_percentage' => 90.0,
    ]);

    // The outer query filters credit_points > 0 → student is not picked up at all.
    $result = $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    expect($result['processed_students'])->toBe(0)
        ->and(GpaCalculation::where('student_id', $student->id)->exists())->toBeFalse();
});

it('finalizes students regardless of their current status, based only on the semester record', function () {
    $intake = ($this->makeStudent)();
    $deferred = Student::factory()->forCampus($this->campus)->create([
        'status' => 'deferred',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->sem1->id,
    ]);

    ($this->makeRecord)($intake, $this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);
    ($this->makeRecord)($deferred, $this->sem1, ['credit_points' => 3.0, 'final_percentage' => 80.0]);

    $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    expect(GpaCalculation::where('student_id', $intake->id)->exists())->toBeTrue()
        ->and(GpaCalculation::where('student_id', $deferred->id)->exists())->toBeTrue();
});
