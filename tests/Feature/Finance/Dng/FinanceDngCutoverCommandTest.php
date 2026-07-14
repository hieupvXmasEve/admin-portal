<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Exceptions\DngCollectionCutoverBlocked;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Support\DngCollectionCutover;
use App\Modules\Finance\Dng\Support\DngReservationTargetFingerprint;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Queries\Dng\DngActiveMigrationInventoryQuery;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cutoverStudent(): Student
{
    $campus = Campus::factory()->create(['dng_code' => 'FAUHN']);
    $semester = Semester::factory()->create();

    return Student::factory()->forCampus($campus)->create([
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
        'status' => 'intake_course',
    ]);
}

function cutoverTarget(Student $student, string $amount = '1000000.00'): InvoiceLine
{
    $billingAccount = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();
    $invoice = $student->invoices()->create([
        'invoice_number' => 'INV-CUTOVER-'.uniqid(),
        'semester_id' => $student->intake_semester_id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
    ]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'finance-test',
        'source_kind' => 'dng-cutover-test',
        'source_ref' => uniqid('cutover:', true),
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $student->intake_semester_id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => $amount,
        'description' => 'DNG cutover target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'DNG cutover target',
        'status' => 'active',
    ]);
}

function legacyDngRequest(Student $student, string $feeType, string $status, array $overrides = []): DngPaymentRequest
{
    $campusCode = $student->campus->getDngCode();
    $chargeId = $overrides['finance_charge_id'] ?? null;
    unset($overrides['finance_charge_id']);

    $request = DngPaymentRequest::query()->create(array_merge([
        'student_id' => $student->id,
        'campus_code' => $campusCode,
        'student_code' => $student->student_id,
        'fee_type' => $feeType,
        'item_id' => 'legacy-'.$feeType.'-'.uniqid(),
        'amount' => '1000000.00',
        'status' => $status,
    ], $overrides));

    if (is_int($chargeId)) {
        DngPaymentRequestCharge::query()->create([
            'dng_payment_request_id' => $request->id,
            'finance_charge_id' => $chargeId,
            'amount' => $request->amount,
        ]);
    }

    return $request;
}

it('inventories active requests without guessing links or writing data', function (): void {
    $student = cutoverStudent();
    $line = cutoverTarget($student);
    legacyDngRequest($student, 'HL', DngPaymentRequest::STATUS_PENDING, [
        'finance_charge_id' => $line->charge_id,
    ]);
    legacyDngRequest($student, 'PTL', DngPaymentRequest::STATUS_PENDING);
    legacyDngRequest($student, 'BHYT', DngPaymentRequest::STATUS_PENDING, [
        'campus_code' => 'OTHER',
    ]);
    legacyDngRequest($student, 'HP', DngPaymentRequest::STATUS_PAID_UNINVOICED, [
        'finance_charge_id' => $line->charge_id,
    ]);
    DngReceiptException::query()->create([
        'evidence_hash' => hash('sha256', 'unmatched-cutover'),
        'exception_type' => 'unmatched_provider_receipt',
        'status' => DngReceiptException::STATUS_OPEN,
        'mismatch_reasons' => ['no local request'],
        'affected_scope' => [],
        'raw_provider_evidence' => ['ItemId' => 'unknown'],
    ]);
    $report = app(DngActiveMigrationInventoryQuery::class)->handle();
    expect($report->counts['paid_unbridged'])->toBe(1)
        ->and($report->counts['unmatched_receipt'])->toBe(1);

    $this->artisan('finance:dng-cutover', ['--json' => true])
        ->expectsOutputToContain('"unknown_link"')
        ->assertExitCode(1);

    expect(DngPaymentRequest::query()->whereNull('billing_account_id')->count())->toBe(4)
        ->and(DngPaymentRequest::query()->whereHas('reservationTargets')->count())->toBe(0);
});

it('backfills exact legacy links idempotently without calling DNG', function (): void {
    $student = cutoverStudent();
    $line = cutoverTarget($student);
    $request = legacyDngRequest($student, 'HL', DngPaymentRequest::STATUS_PENDING, [
        'finance_charge_id' => $line->charge_id,
    ]);

    $this->artisan('finance:dng-cutover', ['--backfill' => true])->assertExitCode(0);

    $request->refresh();
    expect($request->billing_account_id)->toBe(BillingAccount::query()->where('student_id', $student->id)->value('id'))
        ->and($request->provider_rail)->toBe('dng')
        ->and($request->active_slot_key)->toBe('dng:FAUHN:'.$request->billing_account_id.':HL')
        ->and($request->reservationTargets()->count())->toBe(1)
        ->and($request->target_fingerprint)->toBeString()->not->toBeEmpty();

    $target = $request->reservationTargets()->firstOrFail();
    expect($request->target_fingerprint)->toBe(DngReservationTargetFingerprint::make([[
        'invoice_line_id' => $line->id,
        'finance_charge_id' => $line->charge_id,
        'collectible' => '1000000.00',
        'identity' => 'invoice_line:'.$line->id,
    ]]));

    $this->artisan('finance:dng-cutover', ['--backfill' => true])->assertExitCode(0);

    expect($request->fresh()->reservationTargets()->count())->toBe(1)
        ->and($target->fresh()->target_identity)->toBe('invoice_line:'.$line->id);
});

it('blocks cutover when a deferred student still has an unpaid live DNG request', function (): void {
    $student = cutoverStudent();
    $line = cutoverTarget($student);
    $student->update(['status' => 'deferred']);
    $request = legacyDngRequest($student, 'HP', DngPaymentRequest::STATUS_PUSHED_TO_DNG, [
        'finance_charge_id' => $line->charge_id,
    ]);

    $report = app(DngActiveMigrationInventoryQuery::class)->handle();
    $record = collect($report->records)->firstWhere('id', $request->id);

    expect($record['classifications'])->toContain('student_lifecycle_conflict')
        ->and($record['action'])->toBe('cancel_collection')
        ->and($report->isSafeToCutover())->toBeFalse();
});

it('validates a paid request against bridged payment evidence instead of current remaining', function (): void {
    $student = cutoverStudent();
    $line = cutoverTarget($student);
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => '1000000.00',
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    app(SettlementService::class)->createPaymentApplication($payment, $line, 1000000, 'application');
    $request = legacyDngRequest($student, 'HL', DngPaymentRequest::STATUS_PAID_INVOICED, [
        'finance_charge_id' => $line->charge_id,
        'payment_id' => $payment->id,
    ]);

    $report = app(DngActiveMigrationInventoryQuery::class)->handle();
    $record = collect($report->records)->firstWhere('id', $request->id);

    expect($record['classifications'])->toBe(['exact_link'])
        ->and($report->counts['amount_mismatch'] ?? 0)->toBe(0);
});

it('keeps an exact historical paid target after its charge is voided', function (): void {
    $student = cutoverStudent();
    $line = cutoverTarget($student);
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => '1000000.00',
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    app(SettlementService::class)->createPaymentApplication($payment, $line, 1000000, 'application');
    $request = legacyDngRequest($student, 'HL', DngPaymentRequest::STATUS_PAID_INVOICED, [
        'finance_charge_id' => $line->charge_id,
        'payment_id' => $payment->id,
    ]);
    app(VoidFinanceChargeAction::class)->handle($line->charge_id, 'Historical paid target voided', null, false);

    $report = app(DngActiveMigrationInventoryQuery::class)->handle();
    $record = collect($report->records)->firstWhere('id', $request->id);

    expect($record['classifications'])->toBe(['exact_link'])
        ->and($record['target_line_ids'])->toBe([$line->id]);
});

it('keeps exact allocation rows as the only paid-request linkage', function (): void {
    $student = cutoverStudent();
    $line = cutoverTarget($student);
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => '1000000.00',
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    app(SettlementService::class)->createPaymentApplication($payment, $line, 1000000, 'application');
    $request = legacyDngRequest($student, 'HL', DngPaymentRequest::STATUS_PAID_INVOICED, [
        'finance_charge_id' => $line->charge_id,
        'payment_id' => $payment->id,
    ]);

    $report = app(DngActiveMigrationInventoryQuery::class)->handle();
    $record = collect($report->records)->firstWhere('id', $request->id);

    expect($record['classifications'])->toBe(['exact_link'])
        ->and($record['target_line_ids'])->toBe([$line->id]);
});

it('uses payment applications as historical evidence when a paid DNG request has no target link', function (): void {
    $student = cutoverStudent();
    $line = cutoverTarget($student);
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => '1000000.00',
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    app(SettlementService::class)->createPaymentApplication($payment, $line, 1000000, 'application');
    $request = legacyDngRequest($student, 'HL', DngPaymentRequest::STATUS_PAID_INVOICED, [
        'payment_id' => $payment->id,
    ]);

    $report = app(DngActiveMigrationInventoryQuery::class)->handle();
    $record = collect($report->records)->firstWhere('id', $request->id);

    expect($record['classifications'])->toBe(['exact_link'])
        ->and($record['target_line_ids'])->toBe([$line->id]);
});

it('fails closed for new DNG collection while still allowing receipt-side code paths', function (): void {
    config(['finance.dng.enabled' => false]);
    $cutover = app(DngCollectionCutover::class);

    expect($cutover->allowsNewCollection())->toBeFalse();
    expect(fn (): bool => $cutover->assertCollectionAllowed())
        ->toThrow(DngCollectionCutoverBlocked::class);
});
