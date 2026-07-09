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
use App\Modules\Finance\Actions\Major\GenerateMajorChargesAction;
use App\Modules\Finance\Actions\Major\SubmitTuitionTermDebitAction;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Actions\SplitChargeIntoInstallmentsAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *   campus: Campus,
 *   semester: Semester,
 *   student: Student,
 *   plan: TuitionPlan,
 *   term: TuitionPlanTerm,
 *   curriculum_version_id: int,
 *   intake_semester_id: int
 * }
 */
function seedTuitionIntakeScenario(float $termAmount = 45_000_000.0): array
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
        'total_amount' => $termAmount,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    $term = TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => $termAmount,
        'due_date' => '2025-09-15',
    ]);

    session(['current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    return [
        'campus' => $campus,
        'semester' => $semester,
        'student' => $student,
        'plan' => $plan,
        'term' => $term,
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $intakeSemester->id,
    ];
}

it('creates accepted obligation, charge, and invoice line through Batch Studio major path', function (): void {
    $ctx = seedTuitionIntakeScenario();
    $user = User::factory()->create();
    $this->actingAs($user);

    $stats = GenerateMajorChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'student_ids' => [$ctx['student']->id],
    ]);

    expect($stats['created'])->toBe(1)
        ->and($stats['skipped'])->toBe(0)
        ->and($stats['failed'])->toBe(0);

    $obligation = FinanceObligation::query()->firstOrFail();
    $charge = FinanceCharge::query()->where('finance_obligation_id', $obligation->id)->firstOrFail();
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    expect($obligation->source_system)->toBe('finance')
        ->and($obligation->source_kind)->toBe(SubmitTuitionTermDebitAction::SOURCE_KIND_BATCH_STUDIO)
        ->and($obligation->source_ref)->toBe(
            app(SubmitTuitionTermDebitAction::class)->mintSourceRef($ctx['student']->id, $ctx['semester']->id)
        )
        ->and($obligation->obligation_type)->toBe(FinanceCharge::TYPE_TUITION_TERM)
        ->and($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $obligation->amount)->toBe(45_000_000.0)
        ->and($obligation->pricing_snapshot['pricing_source'])->toBe('tuition_plan')
        ->and($charge->charge_type)->toBe(FinanceCharge::TYPE_TUITION_TERM)
        ->and($charge->student_id)->toBe($ctx['student']->id)
        ->and($charge->source_type)->toBeNull()
        ->and($charge->source_id)->toBeNull()
        ->and((float) $charge->amount)->toBe(45_000_000.0)
        ->and((float) $line->amount_snapshot)->toBe(45_000_000.0)
        ->and($charge->description)->toContain('Major Tuition');
});

it('is idempotent for the same tuition source quad via intake', function (): void {
    $ctx = seedTuitionIntakeScenario();
    $this->actingAs(User::factory()->create());

    $submit = app(SubmitTuitionTermDebitAction::class);

    $first = $submit->handle($ctx['student'], $ctx['semester']->id, [
        'source_kind' => SubmitTuitionTermDebitAction::SOURCE_KIND_BATCH_STUDIO,
        'due_date' => '2025-10-01',
    ]);
    $second = $submit->handle($ctx['student'], $ctx['semester']->id, [
        'source_kind' => SubmitTuitionTermDebitAction::SOURCE_KIND_BATCH_STUDIO,
        'due_date' => '2025-10-01',
    ]);

    expect($second->finance_obligation_id)->toBe($first->finance_obligation_id)
        ->and($second->finance_charge_id)->toBe($first->finance_charge_id)
        ->and(FinanceObligation::query()->count())->toBe(1)
        ->and(FinanceCharge::query()->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(1)
        ->and(InvoiceLine::query()->where('charge_id', $first->finance_charge_id)->count())->toBe(1);

    // Batch Studio skip path when active charge already exists
    $stats = GenerateMajorChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'student_ids' => [$ctx['student']->id],
    ]);

    expect($stats['created'])->toBe(0)
        ->and($stats['skipped'])->toBe(1)
        ->and(FinanceCharge::query()->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(1);
});

it('selects the matching pricing catalog rule over tuition plan amount', function (): void {
    $ctx = seedTuitionIntakeScenario(45_000_000.0);
    $this->actingAs(User::factory()->create());

    // Wrong rule for a different term — must not win.
    FinancePricingCatalogItem::query()->create([
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10_000_000,
        'currency' => 'VND',
        'rule_version' => 'tuition_term:wrong-term:v1',
        'description' => 'Wrong term',
        'facts_match' => [
            'curriculum_version_id' => $ctx['curriculum_version_id'],
            'intake_semester_id' => $ctx['intake_semester_id'],
            'term_number' => 99,
        ],
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    // Correct specific rule — wins over TuitionPlan amount.
    FinancePricingCatalogItem::query()->create([
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 50_000_000,
        'currency' => 'VND',
        'rule_version' => 'tuition_term:term1:v1',
        'description' => 'Catalog term 1',
        'facts_match' => [
            'curriculum_version_id' => $ctx['curriculum_version_id'],
            'intake_semester_id' => $ctx['intake_semester_id'],
            'term_number' => 1,
        ],
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    $result = app(SubmitTuitionTermDebitAction::class)->handle($ctx['student'], $ctx['semester']->id, [
        'source_kind' => SubmitTuitionTermDebitAction::SOURCE_KIND_BATCH_STUDIO,
        'due_date' => '2025-10-01',
    ]);

    $obligation = FinanceObligation::query()->findOrFail($result->finance_obligation_id);

    expect((float) $result->amount)->toBe(50_000_000.0)
        ->and((float) $obligation->amount)->toBe(50_000_000.0)
        ->and($obligation->pricing_rule_version)->toBe('tuition_term:term1:v1')
        ->and($obligation->pricing_snapshot['pricing_source'])->toBe('pricing_catalog')
        ->and($obligation->pricing_snapshot['catalog_rule_version'])->toBe('tuition_term:term1:v1');
});

it('keeps installment support for intake-materialized tuition charges', function (): void {
    $ctx = seedTuitionIntakeScenario();
    $this->actingAs(User::factory()->create());

    expect(ObligationTypeRegistry::get(FinanceCharge::TYPE_TUITION_TERM)->supportsInstallments)
        ->toBeTrue();

    $result = app(SubmitTuitionTermDebitAction::class)->handle($ctx['student'], $ctx['semester']->id, [
        'source_kind' => SubmitTuitionTermDebitAction::SOURCE_KIND_BATCH_STUDIO,
        'due_date' => '2025-10-01',
    ]);

    $installments = app(SplitChargeIntoInstallmentsAction::class)->handle($result->finance_charge_id, [
        [
            'installment_no' => 1,
            'amount' => 20_000_000,
            'due_date' => '2025-10-15',
        ],
        [
            'installment_no' => 2,
            'amount' => 25_000_000,
            'due_date' => '2025-11-15',
        ],
    ]);

    expect($installments)->toHaveCount(2)
        ->and(FinanceChargeInstallment::query()->where('finance_charge_id', $result->finance_charge_id)->count())->toBe(2)
        ->and((float) $installments->sum('amount'))->toBe(45_000_000.0);
});

it('skips zero-amount tuition without creating an obligation', function (): void {
    $ctx = seedTuitionIntakeScenario(0.0);
    $this->actingAs(User::factory()->create());

    $stats = GenerateMajorChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'student_ids' => [$ctx['student']->id],
    ]);

    expect($stats['created'])->toBe(0)
        ->and($stats['skipped'])->toBe(1)
        ->and(FinanceObligation::query()->count())->toBe(0)
        ->and(FinanceCharge::query()->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(0);
});

it('rejects caller-supplied final amounts on tuition intake', function (): void {
    $ctx = seedTuitionIntakeScenario();
    $this->actingAs(User::factory()->create());

    app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'finance',
        source_kind: SubmitTuitionTermDebitAction::SOURCE_KIND_BATCH_STUDIO,
        source_ref: 'tuition_term:bypass-amount',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_TUITION_TERM,
        facts: [
            'student_id' => $ctx['student']->id,
            'semester_id' => $ctx['semester']->id,
            'curriculum_version_id' => $ctx['curriculum_version_id'],
            'intake_semester_id' => $ctx['intake_semester_id'],
            'term_number' => 1,
            'amount' => 999,
        ],
    ));
})->throws(InvalidFinanceIntakePayload::class);

it('applies scholarship discount for intake-materialized tuition charges', function (): void {
    $ctx = seedTuitionIntakeScenario();
    $this->actingAs(User::factory()->create());

    $definition = ScholarshipDefinition::create([
        'code' => 'HALF_TUITION',
        'name' => 'Half tuition',
        'description' => '50% scholarship',
        'type' => 'percentage',
        'amount' => 50,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    StudentScholarshipAward::create([
        'student_id' => $ctx['student']->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $stats = GenerateMajorChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'student_ids' => [$ctx['student']->id],
    ]);

    expect($stats['created'])->toBe(1);

    $charge = FinanceCharge::query()
        ->where('student_id', $ctx['student']->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->firstOrFail();

    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();
    $discount = InvoiceDiscount::query()
        ->where('invoice_id', $line->invoice_id)
        ->where('discount_type', 'scholarship')
        ->first();

    expect($discount)->not->toBeNull()
        ->and((float) $discount->amount)->toBe(22_500_000.0);
});

it('routes legacy batch tuition generation through intake', function (): void {
    $ctx = seedTuitionIntakeScenario();
    $this->actingAs(User::factory()->create());

    $result = GenerateBatchChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'scope_type' => 'all',
        'filter_enrollment_status' => 'all',
    ]);

    expect($result['created_count'])->toBeGreaterThanOrEqual(1);

    $obligation = FinanceObligation::query()
        ->where('obligation_type', FinanceCharge::TYPE_TUITION_TERM)
        ->where('source_kind', SubmitTuitionTermDebitAction::SOURCE_KIND_LEGACY_TUITION)
        ->firstOrFail();

    $charge = FinanceCharge::query()
        ->where('finance_obligation_id', $obligation->id)
        ->firstOrFail();

    expect($charge->student_id)->toBe($ctx['student']->id)
        ->and((float) $charge->amount)->toBe(45_000_000.0)
        ->and($charge->source_type)->toBeNull();
});
