<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Modules\Academic\Progression\Queries\GetTranscriptBackfillPreflightQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array{student: Student, campus: Campus, semester: Semester, unit: Unit} */
function transcriptBackfillContext(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    return compact('student', 'campus', 'semester', 'unit');
}

function legacyFinalOutcome(Student $student, Campus $campus, Semester $semester, Unit $unit, array $overrides = []): AcademicRecord
{
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
    ]);

    return AcademicRecord::factory()->create(array_merge([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'program_id' => $student->program_id,
        'campus_id' => $campus->id,
        'attempt_number' => 1,
        'final_percentage' => 82.0,
        'final_letter_grade' => 'B',
        'credit_points' => 3.0,
        'credit_points_earned' => 3.0,
        'quality_points' => 246.0,
        'grade_status' => 'final',
        'completion_status' => 'completed',
        'is_passed' => true,
        'excluded_from_gpa' => false,
        'affects_academic_standing' => true,
        'affects_graduation_requirement' => true,
        'satisfies_prerequisite' => true,
    ], $overrides));
}

function transcriptFromRecord(AcademicRecord $record, array $overrides = []): TranscriptEntry
{
    return TranscriptEntry::query()->create(array_merge([
        'course_result_id' => $record->id,
        'student_id' => $record->student_id,
        'course_offering_id' => $record->course_offering_id,
        'semester_id' => $record->semester_id,
        'unit_id' => $record->unit_id,
        'program_id' => $record->program_id,
        'campus_id' => $record->campus_id,
        'attempt_number' => $record->attempt_number,
        'final_percentage' => $record->final_percentage,
        'final_letter_grade' => $record->final_letter_grade,
        'credit_points' => $record->credit_points,
        'credit_points_earned' => $record->credit_points_earned,
        'quality_points' => $record->quality_points,
        'is_passed' => $record->is_passed,
        'excluded_from_gpa' => $record->excluded_from_gpa,
        'affects_academic_standing' => $record->affects_academic_standing,
        'affects_graduation_requirement' => $record->affects_graduation_requirement,
        'satisfies_prerequisite' => $record->satisfies_prerequisite,
        'finalized_at' => $record->grade_finalized_date,
    ], $overrides));
}

it('reports missing, matching, conflicting, and invalid historical outcomes without writing transcript entries', function () {
    ['student' => $student, 'campus' => $campus, 'semester' => $semester, 'unit' => $unit] = transcriptBackfillContext();
    $missing = legacyFinalOutcome($student, $campus, $semester, $unit);
    $matching = legacyFinalOutcome($student, $campus, $semester, $unit, ['attempt_number' => 2]);
    $conflicting = legacyFinalOutcome($student, $campus, $semester, $unit, ['attempt_number' => 3]);
    legacyFinalOutcome($student, $campus, $semester, $unit, ['attempt_number' => 4, 'final_letter_grade' => null]);
    $dateOnly = legacyFinalOutcome($student, $campus, $semester, $unit, ['attempt_number' => 5, 'grade_finalized_date' => '2026-07-01']);
    transcriptFromRecord($matching);
    transcriptFromRecord($conflicting, ['final_percentage' => 75.0]);

    $report = app(GetTranscriptBackfillPreflightQuery::class)->handle(['student_id' => $student->id]);

    expect($report['counts']['source_final_outcomes'])->toBe(5)
        ->and($report['counts']['ready_to_backfill'])->toBe(1)
        ->and($report['counts']['already_matching'])->toBe(1)
        ->and($report['counts']['conflicts'])->toBe(1)
        ->and($report['counts']['invalid_sources'])->toBe(1)
        ->and($report['counts']['ambiguous_sources'])->toBe(1)
        ->and($report['ready_to_backfill'][0]['course_result_id'])->toBe($missing->id)
        ->and($report['conflicts'][0]['course_result_id'])->toBe($conflicting->id)
        ->and($report['invalid_sources'][0]['course_result_id'])->toBeGreaterThan(0)
        ->and($report['ambiguous_sources'][0]['course_result_id'])->toBe($dateOnly->id)
        ->and(TranscriptEntry::query()->count())->toBe(2);
});

it('requires an explicit preflight scope and has no apply option', function () {
    $this->artisan('academic:preflight-transcript-backfill')
        ->expectsOutputToContain('Specify --student-id, --semester-id, or --all')
        ->assertExitCode(1);

    expect(Artisan::all()['academic:preflight-transcript-backfill']->getDefinition()->hasOption('apply'))->toBeFalse();
});

it('rejects invalid IDs and mutually exclusive scopes before reading evidence', function () {
    $this->artisan('academic:preflight-transcript-backfill', ['--student-id' => 'not-an-id'])
        ->expectsOutputToContain('--student-id must be a positive integer.')
        ->assertExitCode(1);

    $this->artisan('academic:preflight-transcript-backfill', ['--all' => true, '--semester-id' => '1'])
        ->expectsOutputToContain('--all cannot be combined')
        ->assertExitCode(1);
});

it('reports a date-only finalization as a warning after all mappable fields match', function () {
    ['student' => $student, 'campus' => $campus, 'semester' => $semester, 'unit' => $unit] = transcriptBackfillContext();
    $record = legacyFinalOutcome($student, $campus, $semester, $unit, ['grade_finalized_date' => '2026-07-01']);
    transcriptFromRecord($record);

    $report = app(GetTranscriptBackfillPreflightQuery::class)->handle(['student_id' => $student->id]);

    expect($report['counts']['already_matching'])->toBe(1)
        ->and($report['counts']['date_precision_warnings'])->toBe(1)
        ->and($report['counts']['ambiguous_sources'])->toBe(0)
        ->and($report['date_precision_warnings'][0]['course_result_id'])->toBe($record->id);
});

it('compares a UTC transcript timestamp at local calendar-date precision', function () {
    ['student' => $student, 'campus' => $campus, 'semester' => $semester, 'unit' => $unit] = transcriptBackfillContext();
    $record = legacyFinalOutcome($student, $campus, $semester, $unit, ['grade_finalized_date' => '2026-07-01']);
    $transcript = transcriptFromRecord($record);
    DB::table('transcript_entries')
        ->where('id', $transcript->id)
        ->update(['finalized_at' => '2026-06-30 17:00:00']);

    $report = app(GetTranscriptBackfillPreflightQuery::class)->handle(['student_id' => $student->id]);

    expect($report['counts']['already_matching'])->toBe(1)
        ->and($report['counts']['conflicts'])->toBe(0)
        ->and($report['counts']['date_precision_warnings'])->toBe(1);
});
