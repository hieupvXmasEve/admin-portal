<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\StudentRegistry\StudentCollectionEligibilityReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The batch-studio charge generation candidate pool (major/EGC tuition
 * eligibility) is driven by this reader. students.status is a legacy column
 * that program-enrollment transitions don't write back to, so a student who
 * has actually progressed to intake_course must still surface here even
 * though the legacy column lags behind.
 */
it('finds a student via the live study_stage even when students.status is stale', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'status' => 'intake_pre_uni_gc',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);
    ProgramEnrollment::query()->where('student_id', $student->id)->update(['study_stage' => 'intake_course']);

    $ids = app(StudentCollectionEligibilityReader::class)->eligibleStudentIds(['tuition'], $campus->id);

    expect($ids)->toContain($student->id);
});

it('excludes a student whose live study_stage no longer matches, even if students.status still does', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);
    ProgramEnrollment::query()->where('student_id', $student->id)->update(['study_stage' => 'intake_pre_uni_gc']);

    $ids = app(StudentCollectionEligibilityReader::class)->eligibleStudentIds(['tuition'], $campus->id);

    expect($ids)->not->toContain($student->id);
});

it('falls back to the legacy students.status column for a student never materialized', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    expect(ProgramEnrollment::query()->where('student_id', $student->id)->exists())->toBeFalse();

    $ids = app(StudentCollectionEligibilityReader::class)->eligibleStudentIds(['tuition'], $campus->id);

    expect($ids)->toContain($student->id);
});

it('scopes eligible ids to the given campus', function (): void {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create(['status' => 'intake_course', 'intake' => 1, 'intake_semester_id' => $semester->id]);
    $otherCampusStudent = Student::factory()->forCampus($otherCampus)->forProgram($program)->create(['status' => 'intake_course', 'intake' => 1, 'intake_semester_id' => $semester->id]);

    $ids = app(StudentCollectionEligibilityReader::class)->eligibleStudentIds(['tuition'], $campus->id);

    expect($ids)->toContain($student->id)
        ->and($ids)->not->toContain($otherCampusStudent->id);
});
