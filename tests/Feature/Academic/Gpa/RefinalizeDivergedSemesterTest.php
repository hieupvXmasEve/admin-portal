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
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = app(FinalizeSemesterGpaAction::class);
    $this->campus = Campus::factory()->create();
    $this->admin = User::factory()->create();

    $this->sem1 = Semester::factory()->create([
        'code' => 'FALL2025-R',
        'start_date' => Carbon::create(2025, 9, 1),
        'end_date' => Carbon::create(2025, 12, 31),
    ]);
    $this->sem2 = Semester::factory()->create([
        'code' => 'SPRING2026-R',
        'start_date' => Carbon::create(2026, 1, 5),
        'end_date' => Carbon::create(2026, 4, 30),
    ]);

    $this->makeStudent = fn () => Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->sem1->id,
    ]);

    // Returns the created TranscriptEntry so tests can mutate the grade after finalize.
    $this->makeRecord = function (Student $student, Semester $semester, float $creditPoints, float $percentage): TranscriptEntry {
        $unit = Unit::factory()->create(['credit_points' => $creditPoints]);
        $offering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'campus_id' => $student->campus_id,
        ]);
        $record = AcademicRecord::factory()->create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'program_id' => $student->program_id,
            'campus_id' => $student->campus_id,
            'course_offering_id' => $offering->id,
            'credit_points' => $creditPoints,
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
            'credit_points' => $creditPoints,
            'credit_points_earned' => $creditPoints,
            'quality_points' => $percentage * $creditPoints,
            'is_passed' => true,
            'excluded_from_gpa' => false,
            'affects_academic_standing' => true,
            'affects_graduation_requirement' => true,
            'satisfies_prerequisite' => true,
            'finalized_at' => now(),
        ]);
    };
});

it('skips an unchanged re-run without writing or logging', function () {
    $student = ($this->makeStudent)();
    ($this->makeRecord)($student, $this->sem1, 3.0, 80.0);

    $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);
    $before = GpaCalculation::where('student_id', $student->id)->where('semester_id', $this->sem1->id)->first();

    $second = $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);
    $after = GpaCalculation::where('student_id', $student->id)->where('semester_id', $this->sem1->id)->first();

    expect($second['processed_students'])->toBe(0)
        ->and($second['skipped']['already_finalized'])->toBe(1)
        ->and($after->updated_at->equalTo($before->updated_at))->toBeTrue()
        ->and(DB::table('activity_log')->where('log_name', 'gpa_refinalized')->count())->toBe(0);
});

it('updates the row in place and logs old vs new when a grade changed after finalize', function () {
    $student = ($this->makeStudent)();
    $transcript = ($this->makeRecord)($student, $this->sem1, 3.0, 80.0);

    $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    // Grade corrected after finalize; recompute reads from transcript_entries.
    $transcript->update(['final_percentage' => 60.0, 'quality_points' => 60.0 * 3.0]);

    $result = $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    $row = GpaCalculation::where('student_id', $student->id)->where('semester_id', $this->sem1->id)->first();

    expect($result['processed_students'])->toBe(1)
        ->and((float) $row->semester_gpa)->toBe(60.0)
        // Exactly one row for the business key — no duplicate insert.
        ->and(GpaCalculation::withTrashed()->where('student_id', $student->id)->where('semester_id', $this->sem1->id)->count())->toBe(1);

    $log = DB::table('activity_log')->where('log_name', 'gpa_refinalized')->get();
    expect($log)->toHaveCount(1);

    $props = json_decode($log->first()->properties, true);
    expect((float) $props['old']['semester_gpa'])->toBe(80.0)
        ->and((float) $props['new']['semester_gpa'])->toBe(60.0)
        ->and($props['gpa_calculation_id'])->toBe($row->id);
});

it('does not pull is_current back when re-finalizing an earlier semester', function () {
    $student = ($this->makeStudent)();
    $sem1Transcript = ($this->makeRecord)($student, $this->sem1, 3.0, 80.0);
    ($this->makeRecord)($student, $this->sem2, 2.0, 60.0);

    $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);
    $this->action->execute($this->sem2->id, $this->admin->id, $this->campus->id);

    // Correct an earlier-semester grade and re-finalize only that earlier semester.
    $sem1Transcript->update(['final_percentage' => 70.0, 'quality_points' => 70.0 * 3.0]);
    $this->action->execute($this->sem1->id, $this->admin->id, $this->campus->id);

    $sem1Calc = GpaCalculation::where('student_id', $student->id)->where('semester_id', $this->sem1->id)->first();
    $sem2Calc = GpaCalculation::where('student_id', $student->id)->where('semester_id', $this->sem2->id)->first();

    expect((float) $sem1Calc->semester_gpa)->toBe(70.0)
        ->and($sem1Calc->is_current)->toBeFalse()
        ->and($sem2Calc->is_current)->toBeTrue();
});
