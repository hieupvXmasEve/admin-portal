<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Wave 7 / ADR-0030: new debit materialization must not create negative charge rows.
 * Legacy converted rows may remain as voided historical artifacts only.
 */
it('materializer intake creates positive debit charges only (no source morph, no negative amount)', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'manual_fee',
        source_ref: 'guard:'.Str::ulid()->toBase32(),
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_MANUAL_FEE,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'amount' => 250_000,
            'description' => 'Wave-7 negative-charge guard',
        ],
    ));

    $charge = FinanceCharge::query()->findOrFail($result->finance_charge_id);

    expect((float) $charge->amount)->toBeGreaterThan(0)
        ->and($charge->source_type)->toBeNull()
        ->and($charge->source_id)->toBeNull()
        ->and($charge->finance_obligation_id)->not->toBeNull()
        ->and(
            FinanceCharge::query()
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->where('amount', '<', 0)
                ->count()
        )->toBe(0)
        ->and(
            InvoiceLine::query()
                ->where('status', 'active')
                ->where('amount_snapshot', '<', 0)
                ->count()
        )->toBe(0);
});

it('credit intake does not materialize a negative FinanceCharge row', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    $debit = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'manual_fee',
        source_ref: 'guard-debit:'.Str::ulid()->toBase32(),
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_MANUAL_FEE,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'amount' => 1_000_000,
            'description' => 'Guard debit',
        ],
    ));

    app(FinanceIntakeContract::class)->requestCredit(new FinanceIntakeData(
        source_system: 'finance',
        source_kind: 'defer_settlement',
        source_ref: 'guard-credit:'.Str::ulid()->toBase32(),
        financial_effect: FinancialEffect::Credit,
        obligation_type: FinanceCharge::TYPE_DEFER_CREDIT,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'amount' => 100_000,
            'invoice_line_id' => $debit->invoice_line_id,
            'description' => 'Guard credit',
        ],
    ));

    expect(
        FinanceCharge::query()
            ->where('amount', '<', 0)
            ->count()
    )->toBe(0)
        ->and(
            FinanceCharge::query()
                ->where('charge_type', FinanceCharge::TYPE_DEFER_CREDIT)
                ->count()
        )->toBe(0);
});
