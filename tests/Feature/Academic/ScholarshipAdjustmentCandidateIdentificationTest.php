<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\IdentifyCandidatesAction;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Queries\ScholarshipAdjustmentCandidateQuery;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{campus: Campus, source: Semester, target: Semester}
 */
function candidateBaseContext(): array
{
    $campus = Campus::factory()->create();
    $source = Semester::factory()->create(['start_date' => now()->subMonths(6), 'is_archived' => false]);
    $target = Semester::factory()->create(['start_date' => now(), 'is_archived' => false]);

    return compact('campus', 'source', 'target');
}

/**
 * Build a student with an active scholarship award, a failed non-EGC unit in
 * the source semester, and a continuing registration in the target semester —
 * the minimal shape that SHOULD be surfaced as a candidate.
 */
function candidateEligibleStudent(array $ctx, array $recordOverrides = []): array
{
    $student = Student::factory()->create(['campus_id' => $ctx['campus']->id, 'status' => 'intake_course', 'intake' => 1, 'intake_semester_id' => $ctx['source']->id]);

    $definition = ScholarshipDefinition::create([
        'code' => 'CAND'.uniqid(),
        'name' => 'Candidate test scholarship',
        'description' => 'test',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $unit = Unit::factory()->create(['unit_type' => 'general']);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $ctx['source']->id,
        'unit_id' => $unit->id,
        'campus_id' => $ctx['campus']->id,
    ]);

    $record = AcademicRecord::factory()->create(array_merge([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $ctx['source']->id,
        'unit_id' => $unit->id,
        'campus_id' => $ctx['campus']->id,
        'is_passed' => false,
        'override_pass' => false,
        'grade_finalized_date' => now()->subMonth(),
    ], $recordOverrides));

    $targetOffering = CourseOffering::factory()->create([
        'semester_id' => $ctx['target']->id,
        'unit_id' => Unit::factory()->create()->id,
        'campus_id' => $ctx['campus']->id,
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $targetOffering->id,
        'semester_id' => $ctx['target']->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_points' => 3,
        'credit_hours' => 3,
    ]);

    return compact('student', 'definition', 'unit', 'offering', 'record');
}

it('rejects a semester pair with a null start_date instead of silently ordering', function () {
    $ctx = candidateBaseContext();
    $shell = Semester::factory()->create(['start_date' => null]);

    expect(fn () => app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $shell->id, $ctx['target']->id))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects source semester not before target semester', function () {
    $ctx = candidateBaseContext();

    expect(fn () => app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['target']->id, $ctx['source']->id))
        ->toThrow(InvalidArgumentException::class);
});

it('surfaces an eligible failed, non-EGC, finalized, non-overridden, continuing student with an active award', function () {
    $ctx = candidateBaseContext();
    $fixture = candidateEligibleStudent($ctx);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates']->pluck('student_id'))->toContain($fixture['student']->id);
});

it('excludes NULL is_passed and reports the count instead of silently passing it', function () {
    $ctx = candidateBaseContext();
    candidateEligibleStudent($ctx, ['is_passed' => null]);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates'])->toHaveCount(0)
        ->and($result['excluded_null_is_passed'])->toBe(1);
});

it('excludes a record with no grade_finalized_date', function () {
    $ctx = candidateBaseContext();
    candidateEligibleStudent($ctx, ['grade_finalized_date' => null]);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates'])->toHaveCount(0);
});

it('excludes a record with override_pass true', function () {
    $ctx = candidateBaseContext();
    candidateEligibleStudent($ctx, ['override_pass' => true]);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates'])->toHaveCount(0);
});

it('excludes an EGC unit failure', function () {
    $ctx = candidateBaseContext();
    $student = Student::factory()->create(['campus_id' => $ctx['campus']->id, 'status' => 'intake_course', 'intake' => 1, 'intake_semester_id' => $ctx['source']->id]);
    $egcUnit = Unit::factory()->create(['unit_type' => 'egc']);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $ctx['source']->id,
        'unit_id' => $egcUnit->id,
        'campus_id' => $ctx['campus']->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $ctx['source']->id,
        'unit_id' => $egcUnit->id,
        'campus_id' => $ctx['campus']->id,
        'is_passed' => false,
        'override_pass' => false,
        'grade_finalized_date' => now()->subMonth(),
    ]);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates'])->toHaveCount(0);
});

it('excludes a student with no scholarship award', function () {
    $ctx = candidateBaseContext();
    $student = Student::factory()->create(['campus_id' => $ctx['campus']->id, 'status' => 'intake_course', 'intake' => 1, 'intake_semester_id' => $ctx['source']->id]);
    $unit = Unit::factory()->create(['unit_type' => 'general']);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $ctx['source']->id,
        'unit_id' => $unit->id,
        'campus_id' => $ctx['campus']->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $ctx['source']->id,
        'unit_id' => $unit->id,
        'campus_id' => $ctx['campus']->id,
        'is_passed' => false,
        'override_pass' => false,
        'grade_finalized_date' => now()->subMonth(),
    ]);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates'])->toHaveCount(0);
});

it('excludes a record with a pending component-level appeal', function () {
    $ctx = candidateBaseContext();
    $fixture = candidateEligibleStudent($ctx);

    $detail = AssessmentComponentDetail::factory()->create();

    AssessmentComponentDetailScore::create([
        'assessment_component_detail_id' => $detail->id,
        'student_id' => $fixture['student']->id,
        'course_offering_id' => $fixture['offering']->id,
        'appeal_status' => 'pending',
        'appeal_requested' => true,
    ]);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates'])->toHaveCount(0);
});

it('isolates candidates by campus', function () {
    $ctx = candidateBaseContext();
    candidateEligibleStudent($ctx);

    $otherCampus = Campus::factory()->create();

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($otherCampus->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates'])->toHaveCount(0);
});

it('flags needs_data_review for a record finalized before the is_passed gate fix', function () {
    $ctx = candidateBaseContext();
    $fixture = candidateEligibleStudent($ctx, ['grade_finalized_date' => '2026-06-01']);

    $action = app(IdentifyCandidatesAction::class);
    $actor = User::factory()->create();

    $action->run($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id, $actor->id);

    $dossier = ScholarshipAdjustmentDossier::query()->where('student_id', $fixture['student']->id)->first();

    expect($dossier)->not->toBeNull()
        ->and($dossier->needs_data_review)->toBeTrue();
});

it('is idempotent: a second identification run does not duplicate the dossier', function () {
    $ctx = candidateBaseContext();
    $fixture = candidateEligibleStudent($ctx);

    $action = app(IdentifyCandidatesAction::class);
    $actor = User::factory()->create();

    $first = $action->run($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id, $actor->id);
    $second = $action->run($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id, $actor->id);

    expect($first['created'])->toBe(1)
        ->and($second['created'])->toBe(0)
        ->and($second['skipped_existing'])->toBe(1)
        ->and(ScholarshipAdjustmentDossier::query()->where('student_id', $fixture['student']->id)->count())->toBe(1);
});

it('excludes a candidate who already has an active tuition_term charge for the target semester', function () {
    $ctx = candidateBaseContext();
    $fixture = candidateEligibleStudent($ctx);

    FinanceCharge::query()->create([
        'student_id' => $fixture['student']->id,
        'semester_id' => $ctx['target']->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition term',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates'])->toHaveCount(0)
        ->and($result['excluded_already_charged'])->toBe(1);
});

it('keeps a candidate with a voided tuition_term charge for the target semester', function () {
    $ctx = candidateBaseContext();
    $fixture = candidateEligibleStudent($ctx);

    FinanceCharge::query()->create([
        'student_id' => $fixture['student']->id,
        'semester_id' => $ctx['target']->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition term',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_VOID,
    ]);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates']->pluck('student_id'))->toContain($fixture['student']->id)
        ->and($result['excluded_already_charged'])->toBe(0);
});

it('runs identification with no session (artisan context) and produces the same result', function () {
    // Confirms the dedicated query never depends on session('current_campus_id')
    // — FailedStudentsService does, and would silently return zero rows here.
    expect(session()->has('current_campus_id'))->toBeFalse();

    $ctx = candidateBaseContext();
    $fixture = candidateEligibleStudent($ctx);

    $result = app(ScholarshipAdjustmentCandidateQuery::class)
        ->handle($ctx['campus']->id, $ctx['source']->id, $ctx['target']->id);

    expect($result['candidates']->pluck('student_id'))->toContain($fixture['student']->id);
});
