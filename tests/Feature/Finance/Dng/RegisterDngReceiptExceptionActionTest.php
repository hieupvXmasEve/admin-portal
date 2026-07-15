<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\RegisterDngReceiptExceptionAction;
use App\Modules\Finance\Actions\ResolveDngReceiptExceptionAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\DngReceiptException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeReceiptExceptionRequest(string $status = DngPaymentRequest::STATUS_PUSHED_TO_DNG): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 2026,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $billingAccount = BillingAccount::query()->where('student_id', $student->id)->sole();
    $request = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'billing_account_id' => $billingAccount->id,
        'campus_code' => 'HCM',
        'student_code' => 'RECEIPT-001',
        'fee_type' => 'HL',
        'item_id' => 'RECEIPT-ITEM-001',
        'amount' => 100_000,
        'status' => $status,
    ]);

    return compact('billingAccount', 'request');
}

it('keeps unmatched provider evidence in an idempotent queue without guessing a payer', function () {
    $evidence = [
        'PaymentId' => 'PAY-UNMATCHED-001',
        'StudentId' => 'UNKNOWN-STUDENT',
        'ItemId' => 'UNKNOWN-ITEM',
        'Amount' => '100000',
    ];

    $first = RegisterDngReceiptExceptionAction::run([
        'exception_type' => 'unmatched_provider_receipt',
        'mismatch_reasons' => ['No exact Billing Account correlation.'],
        'raw_provider_evidence' => $evidence,
    ]);
    $second = RegisterDngReceiptExceptionAction::run([
        'exception_type' => 'unmatched_provider_receipt',
        'mismatch_reasons' => ['No exact Billing Account correlation.'],
        'raw_provider_evidence' => $evidence,
    ]);

    expect($second->id)->toBe($first->id)
        ->and(DngReceiptException::query()->count())->toBe(1)
        ->and($first->dng_payment_request_id)->toBeNull()
        ->and($first->affected_scope)->toBe([
            'dng_payment_request_id' => null,
            'billing_account_id' => null,
            'invoice_line_ids' => [],
        ])
        ->and($first->raw_provider_evidence)->toBe($evidence);
});

it('resolves an exception idempotently without changing its raw provider evidence', function () {
    $resolver = User::factory()->create();
    $exception = DngReceiptException::query()->create([
        'evidence_hash' => hash('sha256', 'resolve-test'),
        'exception_type' => 'unmatched_provider_receipt',
        'mismatch_reasons' => ['Campus ambiguity.'],
        'affected_scope' => ['dng_payment_request_id' => null, 'billing_account_id' => null, 'invoice_line_ids' => []],
        'raw_provider_evidence' => ['PaymentId' => 'PAY-RESOLVE-001'],
    ]);

    $first = ResolveDngReceiptExceptionAction::run(['exception' => $exception, 'user_id' => $resolver->id]);
    $second = ResolveDngReceiptExceptionAction::run(['exception' => $first, 'user_id' => 456]);

    expect($second->status)->toBe(DngReceiptException::STATUS_RESOLVED)
        ->and($second->resolved_by_user_id)->toBe($resolver->id)
        ->and($second->raw_provider_evidence)->toBe(['PaymentId' => 'PAY-RESOLVE-001']);
});

it('guards a linked collection hold and does not advance again for duplicate evidence', function (): void {
    ['billingAccount' => $billingAccount, 'request' => $request] = makeReceiptExceptionRequest();
    $data = [
        'exception_type' => 'receipt_mismatch',
        'mismatch_reasons' => ['Amount mismatch.'],
        'raw_provider_evidence' => ['PaymentId' => 'PAY-HOLD-001'],
        'request' => $request,
    ];

    RegisterDngReceiptExceptionAction::run($data);

    expect($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and((int) $billingAccount->fresh()->settlement_version)->toBe(1);

    RegisterDngReceiptExceptionAction::run($data);

    expect((int) $billingAccount->fresh()->settlement_version)->toBe(1);
});

it('guards linked exception resolution and leaves an already-resolved retry unchanged', function (): void {
    $resolver = User::factory()->create();
    ['billingAccount' => $billingAccount, 'request' => $request] = makeReceiptExceptionRequest(DngPaymentRequest::STATUS_NEEDS_REVIEW);
    $exception = DngReceiptException::query()->create([
        'evidence_hash' => hash('sha256', 'linked-resolve-test'),
        'exception_type' => 'receipt_mismatch',
        'dng_payment_request_id' => $request->id,
        'mismatch_reasons' => ['Amount mismatch.'],
        'affected_scope' => ['dng_payment_request_id' => $request->id, 'billing_account_id' => $billingAccount->id, 'invoice_line_ids' => []],
        'raw_provider_evidence' => ['PaymentId' => 'PAY-RESOLVE-LINKED-001'],
    ]);

    $resolved = ResolveDngReceiptExceptionAction::run(['exception' => $exception, 'user_id' => $resolver->id]);

    expect($resolved->status)->toBe(DngReceiptException::STATUS_RESOLVED)
        ->and($request->fresh()->status)->toBe(DngPaymentRequest::STATUS_FAILED)
        ->and((int) $billingAccount->fresh()->settlement_version)->toBe(1);

    ResolveDngReceiptExceptionAction::run(['exception' => $resolved, 'user_id' => $resolver->id]);

    expect((int) $billingAccount->fresh()->settlement_version)->toBe(1);
});
