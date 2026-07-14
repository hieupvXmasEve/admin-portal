<?php

declare(strict_types=1);

use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Finance\Actions\Egc\GenerateEgcChargesAction;
use App\Modules\Finance\Actions\Egc\SubmitEgcLevelFeeDebitAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/** @return Collection<int, FinanceCharge> keyed by EGC block id */
function egcIntakeCharges(Collection $blocks): Collection
{
    return app(EgcBlockFinanceResolver::class)->chargesFor($blocks);
}

/**
 * @return array{student: Student, semester: Semester}
 */
function seedEgcIntakeScenario(array $studentState = []): array
{
    $intakeSemester = Semester::factory()->create();
    $cv = CurriculumVersion::factory()->state(['semester_id' => $intakeSemester->id])->create();
    $semester = Semester::factory()->create();

    $student = Student::factory()->state(array_merge([
        'curriculum_version_id' => $cv->id,
        'intake_semester_id' => $intakeSemester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 1,
        'gc_total_levels' => 6,
    ], $studentState))->create();

    return [
        'student' => $student,
        'semester' => $semester,
    ];
}

it('creates accepted obligations and materialized debit rows through Batch Studio EGC generation', function (): void {
    $ctx = seedEgcIntakeScenario();
    $this->actingAs(User::factory()->create());

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'students' => [[
            'student_id' => $ctx['student']->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    expect($results['created'])->toBe(2)
        ->and($results['errors'])->toBeEmpty();

    $obligations = FinanceObligation::query()
        ->where('obligation_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
        ->orderBy('id')
        ->get();

    expect($obligations)->toHaveCount(2);

    foreach ($obligations as $index => $obligation) {
        $level = 1 + $index;
        $charge = FinanceCharge::query()
            ->where('finance_obligation_id', $obligation->id)
            ->firstOrFail();
        $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();
        $block = EgcBlock::query()->findOrFail((int) substr($obligation->source_ref, strlen('egc-block:')));

        expect($obligation->source_system)->toBe(SubmitEgcLevelFeeDebitAction::SOURCE_SYSTEM)
            ->and($obligation->source_kind)->toBe(SubmitEgcLevelFeeDebitAction::SOURCE_KIND_EGC_BLOCK)
            ->and($obligation->source_ref)->toBe(app(EgcBlockFinanceResolver::class)->sourceRef($block))
            ->and($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
            ->and((float) $obligation->amount)->toBe(15_000_000.0)
            ->and($obligation->pricing_snapshot['pricing_source'])->toBe('egc_level_fee_resolver')
            ->and($obligation->pricing_snapshot['level_number'])->toBe($level)
            ->and($obligation->pricing_snapshot['facts']['generation_mode'])->toBe(
                SubmitEgcLevelFeeDebitAction::GENERATION_MODE_FRESH
            )
            ->and($obligation->pricing_snapshot['facts']['block_number'])->toBe($level)
            ->and($charge->charge_type)->toBe(FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->and($charge->student_id)->toBe($ctx['student']->id)
            ->and((float) $charge->amount)->toBe(15_000_000.0)
            ->and((float) $line->amount_snapshot)->toBe(15_000_000.0)
            ->and($block->level_number)->toBe($level)
            ->and($block->block_number)->toBe($level);
    }
});

it('is idempotent for the same egc_level_fee source quad via intake', function (): void {
    $ctx = seedEgcIntakeScenario();
    $this->actingAs(User::factory()->create());

    $submit = app(SubmitEgcLevelFeeDebitAction::class);

    $first = $submit->handle($ctx['student']->id, $ctx['semester']->id, 1, [
        'source_kind' => SubmitEgcLevelFeeDebitAction::SOURCE_KIND_BATCH_STUDIO,
        'due_date' => '2025-10-01',
        'generation_mode' => SubmitEgcLevelFeeDebitAction::GENERATION_MODE_FRESH,
        'block_number' => 1,
    ]);
    $second = $submit->handle($ctx['student']->id, $ctx['semester']->id, 1, [
        'source_kind' => SubmitEgcLevelFeeDebitAction::SOURCE_KIND_BATCH_STUDIO,
        'due_date' => '2025-10-01',
        'generation_mode' => SubmitEgcLevelFeeDebitAction::GENERATION_MODE_FRESH,
        'block_number' => 1,
    ]);

    expect($second->finance_obligation_id)->toBe($first->finance_obligation_id)
        ->and($second->finance_charge_id)->toBe($first->finance_charge_id)
        ->and(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())->toBe(1)
        ->and(FinanceCharge::query()->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())->toBe(1)
        ->and(InvoiceLine::query()->where('charge_id', $first->finance_charge_id)->count())->toBe(1);

});

it('reissues voided EGC block charges through intake without inventing new block numbers', function (): void {
    $ctx = seedEgcIntakeScenario();
    $this->actingAs(User::factory()->create());

    GenerateEgcChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'students' => [[
            'student_id' => $ctx['student']->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    $originalBlocks = EgcBlock::query()
        ->where('student_id', $ctx['student']->id)
        ->where('semester_id', $ctx['semester']->id)
        ->orderBy('block_number')
        ->get();
    $originalChargeIds = egcIntakeCharges($originalBlocks)->pluck('id')->values()->all();
    $originalObligationIds = FinanceCharge::query()
        ->whereIn('id', $originalChargeIds)
        ->pluck('finance_obligation_id')
        ->filter()
        ->values()
        ->all();

    $voider = app(VoidFinanceChargeAction::class);
    foreach ($originalChargeIds as $chargeId) {
        $voider->handle((int) $chargeId, 'reissue setup', null, false);
    }

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'students' => [[
            'student_id' => $ctx['student']->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    $blocks = EgcBlock::query()
        ->where('student_id', $ctx['student']->id)
        ->where('semester_id', $ctx['semester']->id)
        ->orderBy('block_number')
        ->get();

    expect($results['created'])->toBe(2)
        ->and($blocks)->toHaveCount(2)
        ->and($blocks->pluck('id')->all())->toBe($originalBlocks->pluck('id')->all())
        ->and($blocks->pluck('block_number')->all())->toBe([1, 2])
        ->and(egcIntakeCharges($blocks)->pluck('id')->intersect($originalChargeIds)->all())->toBe([]);

    $newCharges = FinanceCharge::query()
        ->whereIn('id', egcIntakeCharges($blocks)->pluck('id'))
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->get();

    expect($newCharges)->toHaveCount(2);

    foreach ($newCharges as $charge) {
        expect($charge->finance_obligation_id)->not->toBeNull()
            ->and(in_array($charge->finance_obligation_id, $originalObligationIds, true))->toBeTrue();

        // Rematerialization reuses the accepted obligation (same source quad);
        // pricing snapshot stays the original accept-time evidence.
        $obligation = FinanceObligation::query()->findOrFail($charge->finance_obligation_id);
        expect($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
            ->and($obligation->pricing_snapshot['pricing_source'])->toBe('egc_level_fee_resolver')
            ->and($obligation->pricing_snapshot['facts']['level_number'] ?? null)
            ->toBe((int) $obligation->pricing_snapshot['level_number']);
    }

    // Same source quads rematerialize — no extra obligations.
    expect(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())->toBe(2);
});

it('does not silently recreate debt outside intake after carry-forward voids unused charges', function (): void {
    $ctx = seedEgcIntakeScenario();
    $user = User::factory()->create();
    $this->actingAs($user);

    GenerateEgcChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'students' => [[
            'student_id' => $ctx['student']->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    $blocks = EgcBlock::query()
        ->where('student_id', $ctx['student']->id)
        ->where('semester_id', $ctx['semester']->id)
        ->orderBy('block_number')
        ->get();

    // Carry-forward adjacency: unused level is explicitly voided (staff action),
    // not silently reused. Debt may return only via intake rematerialization.
    $unusedCharge = app(EgcBlockFinanceResolver::class)->chargeFor($blocks[1]);
    $unusedChargeId = (int) $unusedCharge->id;
    $existingObligation = FinanceObligation::query()->findOrFail($unusedCharge->finance_obligation_id);

    app(VoidFinanceChargeAction::class)->handle(
        $unusedChargeId,
        'EGC carry-forward release: unused charge not consumed by any mapped EGC block.',
        $user->id,
        false,
    );

    expect(FinanceCharge::query()->find($unusedChargeId)?->status)->toBe(FinanceCharge::STATUS_VOID);

    $beforeObligations = FinanceObligation::query()
        ->where('obligation_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
        ->count();

    $submit = app(SubmitEgcLevelFeeDebitAction::class);
    $levelNumber = (int) $blocks[1]->level_number;
    $result = $submit->handle($ctx['student']->id, $ctx['semester']->id, $levelNumber, [
        'source_kind' => $existingObligation->source_kind,
        'source_ref' => $existingObligation->source_ref,
        'due_date' => '2025-11-01',
        'generation_mode' => SubmitEgcLevelFeeDebitAction::GENERATION_MODE_REISSUE,
        'block_number' => (int) $blocks[1]->block_number,
        // Explicit adjacency flag for callers; rematerialization reuses the
        // existing accepted obligation and does not invent a second debt row.
        'carry_forward' => true,
    ]);

    $rematerialized = FinanceCharge::query()->findOrFail($result->finance_charge_id);
    $obligation = FinanceObligation::query()->findOrFail($rematerialized->finance_obligation_id);

    expect($rematerialized->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($rematerialized->id)->not->toBe($unusedChargeId)
        ->and($rematerialized->finance_obligation_id)->not->toBeNull()
        ->and(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)->count())
        ->toBe($beforeObligations)
        ->and($obligation->source_ref)->toBe($existingObligation->source_ref)
        // No second obligation; voided projection replaced via intake only.
        ->and(FinanceCharge::query()
            ->where('finance_obligation_id', $obligation->id)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->count())->toBe(1);
});

it('selects a matching pricing catalog rule over the EGC resolver amount', function (): void {
    $ctx = seedEgcIntakeScenario();
    $this->actingAs(User::factory()->create());

    FinancePricingCatalogItem::query()->create([
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'rule_version' => 'egc-level-1-promo',
        'amount' => 12_000_000,
        'currency' => 'VND',
        'description' => 'EGC L1 promo',
        'facts_match' => ['level_number' => 1],
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'effective_until' => null,
    ]);

    $result = app(SubmitEgcLevelFeeDebitAction::class)->handle(
        $ctx['student']->id,
        $ctx['semester']->id,
        1,
        [
            'source_kind' => SubmitEgcLevelFeeDebitAction::SOURCE_KIND_BATCH_STUDIO,
            'due_date' => '2025-10-01',
            'generation_mode' => SubmitEgcLevelFeeDebitAction::GENERATION_MODE_FRESH,
            'block_number' => 1,
        ],
    );

    $obligation = FinanceObligation::query()->findOrFail($result->finance_obligation_id);
    $charge = FinanceCharge::query()->findOrFail($result->finance_charge_id);

    expect((float) $obligation->amount)->toBe(12_000_000.0)
        ->and($obligation->pricing_rule_version)->toBe('egc-level-1-promo')
        ->and($obligation->pricing_snapshot['pricing_source'])->toBe('pricing_catalog')
        ->and((float) $charge->amount)->toBe(12_000_000.0);
});

it('rejects caller-supplied amount facts for egc_level_fee intake', function (): void {
    $ctx = seedEgcIntakeScenario();
    $this->actingAs(User::factory()->create());

    expect(fn () => app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: SubmitEgcLevelFeeDebitAction::SOURCE_SYSTEM,
        source_kind: SubmitEgcLevelFeeDebitAction::SOURCE_KIND_BATCH_STUDIO,
        source_ref: app(SubmitEgcLevelFeeDebitAction::class)->mintSourceRef(
            $ctx['student']->id,
            $ctx['semester']->id,
            1,
        ),
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_EGC_LEVEL_FEE,
        facts: [
            'student_id' => $ctx['student']->id,
            'semester_id' => $ctx['semester']->id,
            'level_number' => 1,
            'amount' => 1,
            'description' => 'should fail',
        ],
    )))->toThrow(InvalidFinanceIntakePayload::class);
});

it('prices from Unit.base_fee when an EGC unit exists for the level', function (): void {
    $ctx = seedEgcIntakeScenario();
    $this->actingAs(User::factory()->create());

    Unit::factory()->state([
        'unit_type' => 'egc',
        'level' => 1,
        'base_fee' => 18_500_000,
    ])->create();

    $result = app(SubmitEgcLevelFeeDebitAction::class)->handle(
        $ctx['student']->id,
        $ctx['semester']->id,
        1,
        [
            'source_kind' => SubmitEgcLevelFeeDebitAction::SOURCE_KIND_BATCH_STUDIO,
            'generation_mode' => SubmitEgcLevelFeeDebitAction::GENERATION_MODE_FRESH,
        ],
    );

    expect((float) $result->amount)->toBe(18_500_000.0)
        ->and(FinanceObligation::query()->findOrFail($result->finance_obligation_id)
            ->pricing_snapshot['pricing_source'])->toBe('egc_level_fee_resolver');
});

it('preserves retake fact on the pricing snapshot for retake-eligible levels', function (): void {
    $ctx = seedEgcIntakeScenario();
    $priorSemester = Semester::factory()->create();
    $this->actingAs(User::factory()->create());

    EgcBlock::factory()->state([
        'student_id' => $ctx['student']->id,
        'semester_id' => $priorSemester->id,
        'block_number' => 1,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 85.0,
        'retake_discount_id' => null,
    ])->create();

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'due_date' => '2025-10-01',
        'students' => [[
            'student_id' => $ctx['student']->id,
            'block_count' => 1,
            'current_level' => 1,
        ]],
    ]);

    expect($results['created'])->toBe(1);

    $block = EgcBlock::query()
        ->where('student_id', $ctx['student']->id)
        ->where('semester_id', $ctx['semester']->id)
        ->firstOrFail();

    $obligation = FinanceObligation::query()
        ->where('source_ref', app(EgcBlockFinanceResolver::class)->sourceRef($block))
        ->firstOrFail();

    expect($block->is_retake)->toBeTrue()
        ->and($obligation->pricing_snapshot['facts']['is_retake'])->toBeTrue()
        ->and((float) $obligation->amount)->toBe(15_000_000.0);
});
