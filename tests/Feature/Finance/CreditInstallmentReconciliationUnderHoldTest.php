<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\ReconcileChargeInstallmentsAction;
use App\Modules\Finance\Actions\ReverseCreditApplicationAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function heldCreditTarget(): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create(['intake' => 2024, 'intake_semester_id' => $semester->id]);
    $account = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $invoice = StudentInvoice::query()->create(['invoice_number' => 'INV-HOLD-'.uniqid(), 'student_id' => $student->id, 'semester_id' => $semester->id, 'status' => 'pending', 'due_date' => now()->addDays(7)]);
    $obligation = FinanceObligation::query()->create(['billing_account_id' => $account->id, 'source_system' => 'test', 'source_kind' => 'held_credit', 'source_ref' => uniqid('held:', true), 'obligation_type' => FinanceCharge::TYPE_TUITION_TERM, 'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED, 'amount' => 3_000_000, 'currency' => 'VND', 'pricing_rule_version' => 'test', 'pricing_snapshot' => [], 'accepted_at' => now()]);
    $charge = FinanceCharge::query()->create(['finance_obligation_id' => $obligation->id, 'student_id' => $student->id, 'semester_id' => $semester->id, 'charge_type' => FinanceCharge::TYPE_TUITION_TERM, 'amount' => 3_000_000, 'description' => 'Tuition', 'effective_at' => now(), 'status' => FinanceCharge::STATUS_ACTIVE]);
    $line = InvoiceLine::query()->create(['invoice_id' => $invoice->id, 'charge_id' => $charge->id, 'amount_snapshot' => 3_000_000, 'description_snapshot' => 'Tuition', 'status' => 'active']);

    $request = DngPaymentRequest::query()->create(['student_id' => $student->id, 'billing_account_id' => $account->id, 'campus_code' => 'TEST', 'provider_rail' => 'dng', 'student_code' => $student->student_id, 'fee_type' => 'HL', 'description' => 'held', 'semester_id' => $semester->id, 'due_date' => now()->addDays(7), 'item_id' => uniqid('held:', true), 'active_slot_key' => uniqid('slot:', true), 'amount' => 3_000_000, 'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG]);
    DngPaymentRequestReservationTarget::query()->create(['dng_payment_request_id' => $request->id, 'invoice_line_id' => $line->id, 'captured_collectible' => 3_000_000, 'target_identity' => 'invoice_line:'.$line->id]);

    return [$student, $semester, $account, $charge, $line];
}

it('preserves approved credit and skips a live DNG-held target while incrementing the settlement version', function (): void {
    [$student, $semester, $account, , $line] = heldCreditTarget();

    $result = app(FinanceIntakeContract::class)->requestCredit(new FinanceIntakeData(
        source_system: 'test', source_kind: 'held_credit', source_ref: 'held:'.uniqid(),
        financial_effect: FinancialEffect::Credit, obligation_type: FinanceCharge::TYPE_DEFER_CREDIT,
        facts: ['student_id' => $student->id, 'semester_id' => $semester->id, 'amount' => 3_000_000, 'invoice_line_id' => $line->id],
    ));

    expect($result->credit_application_ids)->toBe([])
        ->and(FinanceCreditEntitlement::query()->firstOrFail()->lifecycle_status)->toBe(FinanceCreditEntitlement::STATUS_APPROVED)
        ->and(CreditApplication::query()->count())->toBe(0)
        ->and((int) $account->fresh()->settlement_version)->toBe(1);
});

it('requires a staff-confirmed collection plan before reversing credit with no pending capacity', function (): void {
    [, , $account, $charge, $line] = heldCreditTarget();
    $entitlement = FinanceCreditEntitlement::query()->create(['billing_account_id' => $account->id, 'source_system' => 'test', 'source_kind' => 'reversal', 'source_ref' => uniqid(), 'entitlement_type' => FinanceCharge::TYPE_DEFER_CREDIT, 'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED, 'allocation_status' => FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED, 'amount' => 3_000_000, 'currency' => 'VND', 'pricing_rule_version' => 'test', 'pricing_snapshot' => [], 'approved_at' => now()]);
    $application = CreditApplication::query()->create(['finance_credit_entitlement_id' => $entitlement->id, 'invoice_line_id' => $line->id, 'amount' => 3_000_000, 'entry_type' => CreditApplication::ENTRY_APPLICATION, 'applied_at' => now()]);

    expect(fn () => app(ReverseCreditApplicationAction::class)->handle($application->id))
        ->toThrow(ValidationException::class);
    expect(CreditApplication::query()->count())->toBe(1);
});

it('reverses credit only after staff confirms the amount and due date of a new plan', function (): void {
    [, , $account, $charge, $line] = heldCreditTarget();
    DngPaymentRequest::query()->firstOrFail()->update(['status' => DngPaymentRequest::STATUS_FAILED]);
    $entitlement = FinanceCreditEntitlement::query()->create(['billing_account_id' => $account->id, 'source_system' => 'test', 'source_kind' => 'confirmed-reversal', 'source_ref' => uniqid(), 'entitlement_type' => FinanceCharge::TYPE_DEFER_CREDIT, 'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED, 'allocation_status' => FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED, 'amount' => 3_000_000, 'currency' => 'VND', 'pricing_rule_version' => 'test', 'pricing_snapshot' => [], 'approved_at' => now()]);
    $application = CreditApplication::query()->create(['finance_credit_entitlement_id' => $entitlement->id, 'invoice_line_id' => $line->id, 'amount' => 3_000_000, 'entry_type' => CreditApplication::ENTRY_APPLICATION, 'applied_at' => now()]);

    $reversal = app(ReverseCreditApplicationAction::class)->handle($application->id, [[
        'installment_no' => 1,
        'amount' => 3_000_000,
        'due_date' => now()->addDays(14)->toDateString(),
    ]]);

    expect($reversal->entry_type)->toBe(CreditApplication::ENTRY_REVERSAL)
        ->and((float) $reversal->amount)->toBe(-3_000_000.0)
        ->and((int) $account->fresh()->settlement_version)->toBe(1);
});

it('marks the linked pushed DNG request for review when committed installments exceed canonical collectible', function (): void {
    [, , $account, $charge, $line] = heldCreditTarget();
    FinanceChargeInstallment::query()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 3_000_000,
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
    ]);
    $entitlement = FinanceCreditEntitlement::query()->create([
        'billing_account_id' => $account->id,
        'source_system' => 'test', 'source_kind' => 'committed-over-collect', 'source_ref' => uniqid(),
        'entitlement_type' => FinanceCharge::TYPE_DEFER_CREDIT, 'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED, 'amount' => 2_000_000,
        'currency' => 'VND', 'pricing_rule_version' => 'test', 'pricing_snapshot' => [], 'approved_at' => now(),
    ]);
    CreditApplication::query()->create([
        'finance_credit_entitlement_id' => $entitlement->id,
        'invoice_line_id' => $line->id,
        'amount' => 2_000_000,
        'entry_type' => CreditApplication::ENTRY_APPLICATION,
        'applied_at' => now(),
    ]);

    app(ReconcileChargeInstallmentsAction::class)->handle($charge);

    $request = DngPaymentRequest::query()->firstOrFail()->fresh();
    expect($request->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and($request->error_message)->toContain('committed installments exceed canonical collectible')
        ->and((float) $request->amount)->toBe(3_000_000.0)
        ->and((float) $request->reservationTargets()->firstOrFail()->captured_collectible)->toBe(3_000_000.0)
        ->and($request->canTransitionTo(DngPaymentRequest::STATUS_PAID_UNINVOICED))->toBeTrue();
});
