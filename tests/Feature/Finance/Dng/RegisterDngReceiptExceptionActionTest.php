<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Finance\Actions\RegisterDngReceiptExceptionAction;
use App\Modules\Finance\Actions\ResolveDngReceiptExceptionAction;
use App\Modules\Finance\Models\DngReceiptException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
