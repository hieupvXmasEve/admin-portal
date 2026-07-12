<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Exceptions\DngCollectionCutoverBlocked;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Support\DngCollectionCutover;
use App\Modules\Finance\Dng\Support\DngReservationTargetFingerprint;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Queries\Dng\DngActiveMigrationInventoryQuery;
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

    return DngPaymentRequest::query()->create(array_merge([
        'student_id' => $student->id,
        'campus_code' => $campusCode,
        'student_code' => $student->student_id,
        'fee_type' => $feeType,
        'item_id' => 'legacy-'.$feeType.'-'.uniqid(),
        'amount' => '1000000.00',
        'status' => $status,
    ], $overrides));
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

it('fails closed for new DNG collection while still allowing receipt-side code paths', function (): void {
    config(['finance.dng.collection_mode' => DngCollectionCutover::MODE_OFF]);
    $cutover = app(DngCollectionCutover::class);

    expect($cutover->allowsNewCollection())->toBeFalse();
    expect(fn (): bool => $cutover->assertCollectionAllowed())
        ->toThrow(DngCollectionCutoverBlocked::class);
});
