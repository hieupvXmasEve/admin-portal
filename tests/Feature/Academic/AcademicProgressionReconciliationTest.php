<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\GpaCalculation;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Modules\Academic\Progression\Queries\GetAcademicProgressionReconciliationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('reports a read-only reconciliation and never exposes an apply option', function (): void {
    $report = app(GetAcademicProgressionReconciliationQuery::class)->handle(['all' => true]);

    expect($report['read_only'])->toBeTrue()
        ->and($report['retirement_approval']['recorded'])->toBeFalse()
        ->and(Artisan::all()['academic:audit-progression-reconciliation']->getDefinition()->hasOption('apply'))->toBeFalse()
        ->and(Artisan::all()['academic:audit-progression-reconciliation']->getDefinition()->hasOption('baseline-database'))->toBeTrue();
});

it('detects a persisted GPA mismatch with a stable student-semester key', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create(['start_date' => '2025-01-01']);
    $unit = Unit::factory()->create(['credit_points' => 3]);
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
    ]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'program_id' => $program->id,
        'campus_id' => $campus->id,
        'attempt_number' => 1,
        'final_percentage' => 80,
        'final_letter_grade' => 'B',
        'credit_points' => 3,
        'credit_points_earned' => 3,
        'quality_points' => 240,
        'grade_status' => 'final',
        'completion_status' => 'completed',
        'is_passed' => true,
        'excluded_from_gpa' => false,
        'affects_academic_standing' => true,
        'affects_graduation_requirement' => true,
        'satisfies_prerequisite' => true,
    ]);
    TranscriptEntry::query()->create([
        'course_result_id' => $record->id,
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'program_id' => $program->id,
        'campus_id' => $campus->id,
        'attempt_number' => 1,
        'final_percentage' => 80,
        'final_letter_grade' => 'B',
        'credit_points' => 3,
        'credit_points_earned' => 3,
        'quality_points' => 240,
        'is_passed' => true,
        'excluded_from_gpa' => false,
        'affects_academic_standing' => true,
        'affects_graduation_requirement' => true,
        'satisfies_prerequisite' => true,
    ]);
    GpaCalculation::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'semester_gpa' => 70,
        'cumulative_gpa' => 70,
        'semester_quality_points' => 210,
        'cumulative_quality_points' => 210,
        'semester_credit_points' => 3,
        'cumulative_credit_points' => 3,
        'semester_credit_points_earned' => 3,
        'cumulative_credit_points_earned' => 3,
        'academic_standing' => 'normal',
        'is_finalized' => true,
        'is_current' => true,
    ]);

    $report = app(GetAcademicProgressionReconciliationQuery::class)->handle(['student_id' => $student->id]);

    expect($report['counts']['persisted_gpa_exceptions'])->toBeGreaterThan(0)
        ->and($report['exceptions'][0]['stable_key'])->toBe("student:{$student->id}:semester:{$semester->id}")
        ->and(TranscriptEntry::query()->count())->toBe(1)
        ->and(GpaCalculation::query()->count())->toBe(1);
});

it('returns a non-zero strict result while supported consumers remain', function (): void {
    $this->artisan('academic:audit-progression-reconciliation', ['--all' => true, '--strict' => true])
        ->assertExitCode(1);
});

it('rejects an unscoped audit and invalid format', function (): void {
    $this->artisan('academic:audit-progression-reconciliation')
        ->expectsOutputToContain('Specify --student-id, --semester-id, or --all')
        ->assertExitCode(1);

    $this->artisan('academic:audit-progression-reconciliation', ['--all' => true, '--format' => 'csv'])
        ->expectsOutputToContain('--format must be table or json.')
        ->assertExitCode(1);
});

it('rejects mutually exclusive scope through the public reconciliation query', function (): void {
    expect(fn (): array => app(GetAcademicProgressionReconciliationQuery::class)->handle([
        'all' => true,
        'student_id' => 1,
    ]))->toThrow(InvalidArgumentException::class, '--all cannot be combined');
});

it('restores the current connection after an unavailable baseline database', function (): void {
    $this->artisan('academic:audit-progression-reconciliation', [
        '--all' => true,
        '--baseline-database' => 'missing_issue24_baseline_database',
    ])
        ->expectsOutputToContain("Unable to read baseline database 'missing_issue24_baseline_database'")
        ->assertExitCode(1);

    expect(DB::table('students')->count())->toBe(0);
});
