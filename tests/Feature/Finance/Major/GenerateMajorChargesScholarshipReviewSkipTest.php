<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Finance\Actions\Major\GenerateMajorChargesAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the ACTUAL live batch-studio "major" commit path
 * (BatchStudioController::commitCharges -> GenerateMajorChargesAction), as
 * distinct from the legacy GenerateBatchChargesAction used only by
 * FixBillingExceptionAction.
 */
function seedMajorScholarshipReviewSkipStudent(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create([
        'start_date' => '2025-09-01 00:00:00',
        'end_date' => '2025-12-31 00:00:00',
        'is_active' => true,
    ]);
    $intakeSemester = Semester::factory()->create([
        'start_date' => '2025-05-01 00:00:00',
        'end_date' => '2025-08-31 00:00:00',
    ]);
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($intakeSemester)
        ->create();

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'status' => 'intake_course',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $intakeSemester->id,
            'intake_course' => (string) $intakeSemester->id,
            'intake_major' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ]);

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $intakeSemester->id,
        'total_amount' => 45000000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 45000000,
        'due_date' => '2025-09-15',
    ]);

    session(['current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    return compact('student', 'semester', 'campus');
}

it('skips tuition_term generation via GenerateMajorChargesAction for an in-flight scholarship dossier', function () {
    $ctx = seedMajorScholarshipReviewSkipStudent();
    $maker = User::factory()->create();

    ScholarshipAdjustmentDossier::query()->create([
        'student_id' => $ctx['student']->id,
        'campus_id' => $ctx['campus']->id,
        'source_semester_id' => $ctx['semester']->id,
        'target_semester_id' => $ctx['semester']->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => 'SCH-01',
        'original_type' => 'percentage',
        'original_amount' => 10,
        'created_by_user_id' => $maker->id,
    ]);

    $stats = GenerateMajorChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'student_ids' => [$ctx['student']->id],
    ]);

    expect($stats['created'])->toBe(0)
        ->and($stats['skipped'])->toBe(1)
        ->and($stats['deferred_scholarship_review'])->toBe([$ctx['student']->id])
        ->and(FinanceCharge::query()->where('student_id', $ctx['student']->id)->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)->exists())->toBeFalse();
});

it('does not defer or notify a student whose tuition was not due this semester anyway, even with an in-flight dossier', function () {
    // Intake_major set to a LATER semester than the target — tuition is not
    // yet due for the target semester regardless of the scholarship review,
    // so this student must not be reported as "deferred" (that would wrongly
    // tell them tuition is on hold when none was ever going to be generated).
    $ctx = seedMajorScholarshipReviewSkipStudent();
    $laterSemester = Semester::factory()->create(['start_date' => '2026-01-01 00:00:00']);
    $ctx['student']->update(['intake_major' => $laterSemester->id]);
    $maker = User::factory()->create();

    ScholarshipAdjustmentDossier::query()->create([
        'student_id' => $ctx['student']->id,
        'campus_id' => $ctx['campus']->id,
        'source_semester_id' => $ctx['semester']->id,
        'target_semester_id' => $ctx['semester']->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => 'SCH-01',
        'original_type' => 'percentage',
        'original_amount' => 10,
        'created_by_user_id' => $maker->id,
    ]);

    $stats = GenerateMajorChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'student_ids' => [$ctx['student']->id],
    ]);

    expect($stats['created'])->toBe(0)
        ->and($stats['deferred_scholarship_review'])->toBe([]);
});

it('still generates tuition via GenerateMajorChargesAction with no in-flight dossier (regression)', function () {
    $ctx = seedMajorScholarshipReviewSkipStudent();

    $stats = GenerateMajorChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'student_ids' => [$ctx['student']->id],
    ]);

    expect($stats['created'])->toBe(1)
        ->and($stats['deferred_scholarship_review'])->toBe([]);
});

/**
 * Directly creates an `applied` carried adjustment (prior semester) + a
 * pending_approval restoration proposal on it — bypasses the full
 * ScholarshipAdjustmentContract::apply() maker/checker pipeline (irrelevant
 * here) the same way GenerateBatchChargesScholarshipAdjustmentTest's
 * seedBatchAdjustment() does.
 */
function seedCarriedAdjustmentWithPendingRestoration(array $ctx, ?int $targetSemesterId = null): ScholarshipSemesterAdjustment
{
    $priorSemester = Semester::factory()->create(['start_date' => '2025-01-01 00:00:00']);
    $sourceSemester = Semester::factory()->create(['start_date' => '2024-05-01 00:00:00']);

    $definition = ScholarshipDefinition::create([
        'code' => 'MAJORGATE'.uniqid(), 'name' => 'Major gate test', 'description' => '30%',
        'type' => 'percentage', 'amount' => 30,
        'valid_from' => now()->subYears(2)->toDateString(), 'valid_until' => now()->addYears(2)->toDateString(), 'is_active' => true,
    ]);
    $award = StudentScholarshipAward::create([
        'student_id' => $ctx['student']->id, 'scholarship_code' => $definition->code, 'awarded_at' => now()->toDateString(),
    ]);
    $maker = User::factory()->create();
    $checker = User::factory()->create();

    $adjustment = ScholarshipSemesterAdjustment::create([
        'student_id' => $ctx['student']->id,
        'campus_id' => $ctx['campus']->id,
        'student_scholarship_award_id' => $award->id,
        'scholarship_code' => $definition->code,
        'source_semester_id' => $sourceSemester->id,
        'target_semester_id' => $targetSemesterId ?? $priorSemester->id,
        'original_type' => $definition->type,
        'original_amount' => $definition->amount,
        'award_fingerprint' => ScholarshipAdjustmentData::fingerprint($definition->code, $definition->type, (string) $definition->amount),
        'adjusted_amount' => 15,
        'status' => ScholarshipSemesterAdjustment::STATUS_APPLIED,
        'reason' => 'Major gate test',
        'academic_dossier_id' => 9001,
        'created_by_user_id' => $maker->id,
        'approved_by_user_id' => $checker->id,
        'applied_at' => now(),
    ]);

    ScholarshipRestorationProposal::create([
        'scholarship_semester_adjustment_id' => $adjustment->id,
        'student_id' => $ctx['student']->id,
        'campus_id' => $ctx['campus']->id,
        'evaluated_semester_id' => $adjustment->target_semester_id,
        'status' => ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL,
        'reason' => 'Clean result',
        'proposed_by_user_id' => $maker->id,
    ]);

    return $adjustment;
}

it('skips tuition_term generation via GenerateMajorChargesAction for a carried adjustment with a pending restoration proposal', function () {
    $ctx = seedMajorScholarshipReviewSkipStudent();
    seedCarriedAdjustmentWithPendingRestoration($ctx);

    $stats = GenerateMajorChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'student_ids' => [$ctx['student']->id],
    ]);

    expect($stats['created'])->toBe(0)
        ->and($stats['skipped'])->toBe(1)
        ->and($stats['deferred_scholarship_restoration_pending'])->toBe([$ctx['student']->id])
        ->and(FinanceCharge::query()->where('student_id', $ctx['student']->id)->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)->exists())->toBeFalse();
});

it('does NOT block generation when the student has their OWN active adjustment for the current semester, even with an older pending restoration proposal', function () {
    // The current-semester adjustment governs this semester's money — an
    // older carried adjustment's pending restoration is irrelevant to it.
    $ctx = seedMajorScholarshipReviewSkipStudent();
    seedCarriedAdjustmentWithPendingRestoration($ctx);

    // A student has exactly one scholarship award — reuse it (a second
    // adjustment against the same award, for a later semester, is exactly
    // what carry-forward means; a second AWARD is not allowed by the model).
    $award = StudentScholarshipAward::where('student_id', $ctx['student']->id)->firstOrFail();
    $definition = ScholarshipDefinition::where('code', $award->scholarship_code)->firstOrFail();
    $maker = User::factory()->create();
    $earlierSemester = Semester::factory()->create(['start_date' => '2025-06-01 00:00:00']);

    ScholarshipSemesterAdjustment::create([
        'student_id' => $ctx['student']->id,
        'campus_id' => $ctx['campus']->id,
        'student_scholarship_award_id' => $award->id,
        'scholarship_code' => $definition->code,
        'source_semester_id' => $earlierSemester->id,
        'target_semester_id' => $ctx['semester']->id,
        'original_type' => $definition->type,
        'original_amount' => $definition->amount,
        'award_fingerprint' => ScholarshipAdjustmentData::fingerprint($definition->code, $definition->type, (string) $definition->amount),
        'adjusted_amount' => 10,
        'status' => ScholarshipSemesterAdjustment::STATUS_APPLIED,
        'reason' => 'Current semester adjustment',
        'academic_dossier_id' => 9002,
        'created_by_user_id' => $maker->id,
        'approved_by_user_id' => $maker->id,
        'applied_at' => now(),
    ]);

    $stats = GenerateMajorChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'student_ids' => [$ctx['student']->id],
    ]);

    expect($stats['created'])->toBe(1)
        ->and($stats['deferred_scholarship_restoration_pending'])->toBe([]);
});
