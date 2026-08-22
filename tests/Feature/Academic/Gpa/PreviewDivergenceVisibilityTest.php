<?php

declare(strict_types=1);

use App\Actions\Academic\FinalizeSemesterGpaAction;
use App\Actions\Academic\PreviewSemesterGpaAction;
use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Modules\Academic\Progression\Queries\GetAcademicProgressionReconciliationQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->finalize = app(FinalizeSemesterGpaAction::class);
    $this->preview = app(PreviewSemesterGpaAction::class);
    $this->campus = Campus::factory()->create();
    $this->admin = User::factory()->create();

    $this->sem = Semester::factory()->create([
        'code' => 'FALL2025-P',
        'start_date' => Carbon::create(2025, 9, 1),
        'end_date' => Carbon::create(2025, 12, 31),
    ]);

    $this->makeStudent = fn () => Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->sem->id,
    ]);

    $this->makeRecord = function (Student $student, float $percentage): TranscriptEntry {
        $unit = Unit::factory()->create(['credit_points' => 3.0]);
        $offering = CourseOffering::factory()->create([
            'semester_id' => $this->sem->id,
            'unit_id' => $unit->id,
            'campus_id' => $student->campus_id,
        ]);
        $record = AcademicRecord::factory()->create([
            'student_id' => $student->id,
            'semester_id' => $this->sem->id,
            'unit_id' => $unit->id,
            'program_id' => $student->program_id,
            'campus_id' => $student->campus_id,
            'course_offering_id' => $offering->id,
            'credit_points' => 3.0,
            'final_percentage' => $percentage,
            'grade_status' => 'final',
            'excluded_from_gpa' => false,
            'is_passed' => true,
        ]);

        return TranscriptEntry::query()->create([
            'course_result_id' => $record->id,
            'student_id' => $record->student_id,
            'course_offering_id' => $record->course_offering_id,
            'semester_id' => $record->semester_id,
            'unit_id' => $record->unit_id,
            'program_id' => $record->program_id,
            'campus_id' => $record->campus_id,
            'attempt_number' => 1,
            'final_percentage' => $percentage,
            'final_letter_grade' => $record->final_letter_grade,
            'credit_points' => 3.0,
            'credit_points_earned' => 3.0,
            'quality_points' => $percentage * 3.0,
            'is_passed' => true,
            'excluded_from_gpa' => false,
            'affects_academic_standing' => true,
            'affects_graduation_requirement' => true,
            'satisfies_prerequisite' => true,
            'finalized_at' => now(),
        ]);
    };

    $this->rowFor = fn (array $preview, Student $student): array => collect($preview)
        ->firstWhere('id', $student->id);
});

it('flags a finalized student whose grade changed after finalize as divergent', function () {
    $diverged = ($this->makeStudent)();
    $matching = ($this->makeStudent)();
    $divergedTranscript = ($this->makeRecord)($diverged, 80.0);
    ($this->makeRecord)($matching, 70.0);

    $this->finalize->execute($this->sem->id, $this->admin->id, $this->campus->id);

    // A never-finalized student appears only after finalize ran.
    $neverFinalized = ($this->makeStudent)();
    ($this->makeRecord)($neverFinalized, 90.0);

    // Correct the diverged student's grade after finalize.
    $divergedTranscript->update(['final_percentage' => 60.0, 'quality_points' => 60.0 * 3.0]);

    $preview = $this->preview->execute($this->sem->id, $this->campus->id);

    $divergedRow = ($this->rowFor)($preview, $diverged);
    expect($divergedRow['is_divergent'])->toBeTrue()
        ->and((float) $divergedRow['stored_semester_gpa'])->toBe(80.0)
        ->and($divergedRow['semester_gpa'])->toBe(60.0);

    $matchingRow = ($this->rowFor)($preview, $matching);
    expect($matchingRow['is_divergent'])->toBeFalse()
        ->and((float) $matchingRow['stored_semester_gpa'])->toBe(70.0);

    $neverRow = ($this->rowFor)($preview, $neverFinalized);
    expect($neverRow['is_divergent'])->toBeFalse()
        ->and($neverRow['stored_semester_gpa'])->toBeNull()
        ->and($neverRow['stored_cumulative_gpa'])->toBeNull();
});

it('marks the same students divergent as the GPA-001 audit for the same scope', function () {
    $diverged = ($this->makeStudent)();
    $matching = ($this->makeStudent)();
    $divergedTranscript = ($this->makeRecord)($diverged, 80.0);
    ($this->makeRecord)($matching, 70.0);

    $this->finalize->execute($this->sem->id, $this->admin->id, $this->campus->id);
    $divergedTranscript->update(['final_percentage' => 60.0, 'quality_points' => 60.0 * 3.0]);

    $pageDivergent = collect($this->preview->execute($this->sem->id, $this->campus->id))
        ->where('is_divergent', true)
        ->pluck('id')->sort()->values()->all();

    $report = app(GetAcademicProgressionReconciliationQuery::class)->handle(['semester_id' => $this->sem->id]);
    $auditDivergent = collect($report['exceptions'])
        ->where('reason', 'persisted_value_differs_from_transcript_derived_value')
        ->map(fn (array $e): int => (int) explode(':', $e['stable_key'])[1])
        ->unique()->sort()->values()->all();

    expect($pageDivergent)->toBe($auditDivergent)
        ->and($pageDivergent)->toBe([$diverged->id]);
});

it('clears divergence after a re-finalize', function () {
    $student = ($this->makeStudent)();
    $transcript = ($this->makeRecord)($student, 80.0);

    $this->finalize->execute($this->sem->id, $this->admin->id, $this->campus->id);
    $transcript->update(['final_percentage' => 60.0, 'quality_points' => 60.0 * 3.0]);

    expect(($this->rowFor)($this->preview->execute($this->sem->id, $this->campus->id), $student)['is_divergent'])->toBeTrue();

    $this->finalize->execute($this->sem->id, $this->admin->id, $this->campus->id);

    $row = ($this->rowFor)($this->preview->execute($this->sem->id, $this->campus->id), $student);
    expect($row['is_divergent'])->toBeFalse()
        ->and((float) $row['stored_semester_gpa'])->toBe(60.0);
});
