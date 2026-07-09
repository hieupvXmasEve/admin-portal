<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use App\Shared\Contracts\Finance\Exceptions\UnsupportedFinancialEffectYet;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);

    DB::table('finance_pricing_catalog_items')->insert([
        [
            'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
            'amount' => 1_500_000,
            'currency' => 'VND',
            'rule_version' => 'retake_fee:v1',
            'description' => 'Fixed retake fee',
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
            'amount' => 750_000,
            'currency' => 'VND',
            'rule_version' => 'exam_resit_fee:v1',
            'description' => 'Fixed resit fee',
            'is_active' => true,
            'effective_from' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
});

it('prices and materializes a debit obligation into one finance charge and invoice line', function (): void {
    $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'course_retake_registration',
        source_ref: 'RET-2026-0001',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_RETAKE_FEE,
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'description' => 'Retake fee for source RET-2026-0001',
        ],
    ));

    $obligation = FinanceObligation::query()->firstOrFail();
    $charge = FinanceCharge::query()->where('finance_obligation_id', $obligation->id)->firstOrFail();
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    expect($result->finance_obligation_id)->toBe($obligation->id)
        ->and($result->finance_charge_id)->toBe($charge->id)
        ->and($result->invoice_line_id)->toBe($line->id)
        ->and($obligation->source_system)->toBe('academic')
        ->and($obligation->source_kind)->toBe('course_retake_registration')
        ->and($obligation->source_ref)->toBe('RET-2026-0001')
        ->and($obligation->obligation_type)->toBe(FinanceCharge::TYPE_RETAKE_FEE)
        ->and($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $obligation->amount)->toBe(1_500_000.0)
        ->and($obligation->currency)->toBe('VND')
        ->and($obligation->pricing_rule_version)->toBe('retake_fee:v1')
        ->and($obligation->pricing_snapshot['catalog_rule_version'])->toBe('retake_fee:v1')
        ->and($charge->charge_type)->toBe(FinanceCharge::TYPE_RETAKE_FEE)
        ->and((float) $charge->amount)->toBe(1_500_000.0)
        ->and($charge->source_type)->toBeNull()
        ->and($charge->source_id)->toBeNull()
        ->and((float) $line->amount_snapshot)->toBe(1_500_000.0);
});

it('is idempotent for the same source quad', function (): void {
    $intake = new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'exam_resit_attempt',
        source_ref: 'RESIT-2026-0001',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_EXAM_RESIT_FEE,
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'description' => 'Exam resit fee for source RESIT-2026-0001',
        ],
    );

    $contract = app(FinanceIntakeContract::class);

    $first = $contract->request($intake);
    $second = $contract->requestDebit($intake);

    expect($second->finance_obligation_id)->toBe($first->finance_obligation_id)
        ->and($second->finance_charge_id)->toBe($first->finance_charge_id)
        ->and(FinanceObligation::query()->count())->toBe(1)
        ->and(FinanceCharge::query()->where('finance_obligation_id', $first->finance_obligation_id)->count())->toBe(1)
        ->and(InvoiceLine::query()->where('charge_id', $first->finance_charge_id)->count())->toBe(1);
});

it('rejects source supplied pricing values', function (): void {
    app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'course_retake_registration',
        source_ref: 'RET-2026-PRICE-BYPASS',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_RETAKE_FEE,
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'amount' => 999,
        ],
    ));
})->throws(InvalidFinanceIntakePayload::class);

it('rejects credit intake until that branch exists', function (): void {
    app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'future_source',
        source_ref: 'FUTURE-1',
        financial_effect: FinancialEffect::Credit,
        obligation_type: 'future_entitlement',
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
        ],
    ));
})->throws(UnsupportedFinancialEffectYet::class);

it('rejects discount intake until that branch exists', function (): void {
    app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'future_source',
        source_ref: 'FUTURE-1',
        financial_effect: FinancialEffect::Discount,
        obligation_type: 'future_entitlement',
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
        ],
    ));
})->throws(UnsupportedFinancialEffectYet::class);
