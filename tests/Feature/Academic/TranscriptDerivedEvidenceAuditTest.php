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
use App\Modules\Academic\Progression\Queries\GetTranscriptDerivedEvidenceAuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/** @return array{student: Student, campus: Campus, program: Program, firstSemester: Semester, secondSemester: Semester} */
function derivedAuditContext(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $secondSemester = Semester::factory()->create(['start_date' => '2025-08-01']);
    $firstSemester = Semester::factory()->create(['start_date' => '2025-01-01']);
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $firstSemester->id,
    ]);

    return compact('student', 'campus', 'program', 'firstSemester', 'secondSemester');
}

function derivedAuditOutcome(Student $student, Campus $campus, Semester $semester, Unit $unit, array $overrides = []): AcademicRecord
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
        'final_percentage' => 80.0,
        'final_letter_grade' => 'B',
        'credit_points' => 3.0,
        'credit_points_earned' => 3.0,
        'quality_points' => 240.0,
        'grade_status' => 'final',
        'completion_status' => 'completed',
        'is_passed' => true,
        'excluded_from_gpa' => false,
        'affects_academic_standing' => true,
        'affects_graduation_requirement' => true,
        'satisfies_prerequisite' => true,
    ], $overrides));
}

function derivedAuditTranscript(AcademicRecord $record, array $overrides = []): TranscriptEntry
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
        'finalized_at' => null,
    ], $overrides));
}

it('reconciles derived GPA, standing, and best attempts without writing', function () {
    ['student' => $student, 'campus' => $campus, 'firstSemester' => $firstSemester, 'secondSemester' => $secondSemester] = derivedAuditContext();
    $first = derivedAuditOutcome($student, $campus, $firstSemester, Unit::factory()->create(), ['final_percentage' => 80.0]);
    $second = derivedAuditOutcome($student, $campus, $secondSemester, Unit::factory()->create(), [
        'final_percentage' => 40.0,
        'final_letter_grade' => 'P',
        'credit_points_earned' => 3.0,
        'quality_points' => 120.0,
        'is_passed' => true,
    ]);
    derivedAuditTranscript($first);
    $secondTranscript = derivedAuditTranscript($second);

    $matching = app(GetTranscriptDerivedEvidenceAuditQuery::class)->handle(['student_id' => $student->id]);

    expect($matching['counts']['gpa_rows'])->toBe(2)
        ->and($matching['counts']['gpa_mismatches'])->toBe(0)
        ->and($matching['counts']['standing_mismatches'])->toBe(0)
        ->and($matching['counts']['best_attempts'])->toBe(2)
        ->and($matching['counts']['best_attempt_mismatches'])->toBe(0)
        ->and(TranscriptEntry::query()->count())->toBe(2);

    $secondTranscript->update(['final_percentage' => 20.0]);

    $mismatched = app(GetTranscriptDerivedEvidenceAuditQuery::class)->handle(['student_id' => $student->id]);

    expect($mismatched['counts']['gpa_mismatches'])->toBe(1)
        ->and($mismatched['counts']['best_attempt_mismatches'])->toBe(1)
        ->and($mismatched['gpa_mismatches'][0]['semester_id'])->toBe($secondSemester->id);
});

it('uses earlier semesters when reconciling a semester-scoped cumulative GPA', function () {
    ['student' => $student, 'campus' => $campus, 'firstSemester' => $firstSemester, 'secondSemester' => $secondSemester] = derivedAuditContext();
    $first = derivedAuditOutcome($student, $campus, $firstSemester, Unit::factory()->create(), ['final_percentage' => 80.0]);
    $second = derivedAuditOutcome($student, $campus, $secondSemester, Unit::factory()->create(), ['final_percentage' => 40.0]);
    $firstTranscript = derivedAuditTranscript($first);
    derivedAuditTranscript($second);

    $firstTranscript->update(['final_percentage' => 20.0]);

    $report = app(GetTranscriptDerivedEvidenceAuditQuery::class)->handle(['semester_id' => $secondSemester->id]);

    expect($report['counts']['gpa_rows'])->toBe(1)
        ->and($report['counts']['gpa_mismatches'])->toBe(1)
        ->and($report['counts']['best_attempts'])->toBe(1)
        ->and($report['gpa_mismatches'][0]['semester_id'])->toBe($secondSemester->id);
});

it('reconciles the Student Hub latest finalized attempt even when it failed', function () {
    ['student' => $student, 'campus' => $campus, 'firstSemester' => $firstSemester, 'secondSemester' => $secondSemester] = derivedAuditContext();
    $unit = Unit::factory()->create();
    $first = derivedAuditOutcome($student, $campus, $firstSemester, $unit, [
        'attempt_number' => 2,
        'final_percentage' => 80.0,
        'grade_finalized_date' => '2025-01-01',
    ]);
    $latest = derivedAuditOutcome($student, $campus, $secondSemester, $unit, [
        'attempt_number' => 1,
        'final_percentage' => 40.0,
        'credit_points_earned' => 0.0,
        'quality_points' => 120.0,
        'is_passed' => false,
        'grade_finalized_date' => '2025-08-01',
    ]);
    derivedAuditTranscript($first, ['finalized_at' => '2025-01-01 00:00:00']);
    $latestTranscript = derivedAuditTranscript($latest, ['finalized_at' => '2025-08-01 00:00:00']);

    $latestTranscript->update(['final_percentage' => 20.0]);

    $report = app(GetTranscriptDerivedEvidenceAuditQuery::class)->handle(['student_id' => $student->id]);

    expect($report['counts']['best_attempts'])->toBe(1)
        ->and($report['counts']['best_attempt_mismatches'])->toBe(1);
});

it('requires an explicit valid audit scope and has no apply option', function () {
    $this->artisan('academic:audit-transcript-derived')
        ->expectsOutputToContain('Specify --student-id, --semester-id, or --all')
        ->assertExitCode(1);

    $this->artisan('academic:audit-transcript-derived', ['--student-id' => 'not-an-id'])
        ->expectsOutputToContain('--student-id must be a positive integer.')
        ->assertExitCode(1);

    $this->artisan('academic:audit-transcript-derived', ['--all' => true, '--format' => 'csv'])
        ->expectsOutputToContain('--format must be table or json.')
        ->assertExitCode(1);

    expect(Artisan::all()['academic:audit-transcript-derived']->getDefinition()->hasOption('apply'))->toBeFalse();
});

it('emits machine-readable read-only JSON', function () {
    $this->artisan('academic:audit-transcript-derived', ['--all' => true, '--format' => 'json'])
        ->expectsOutputToContain('"read_only": true')
        ->doesntExpectOutputToContain('READ-ONLY:')
        ->assertExitCode(0);
});
