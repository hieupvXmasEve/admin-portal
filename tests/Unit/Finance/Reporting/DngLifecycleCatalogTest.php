<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Support\Reporting\DngLifecycleCatalog as Catalog;
use Illuminate\Support\Carbon;

it('exposes the accepted attention-bucket vocabulary', function () {
    expect(Catalog::attentionBucketKeys())->toBe([
        'failed_request',
        'webhook_problem',
        'paid_uninvoiced',
        'pending_stale',
        'overdue_pushed',
    ])
        ->and(Catalog::attentionBucketLabel('failed_request'))->toBe('Yêu cầu thất bại');
});

it('collapses webhook events into a worst-first aggregate state', function () {
    expect(Catalog::deriveWebhookState([]))->toBe(Catalog::WEBHOOK_NONE)
        ->and(Catalog::deriveWebhookState([
            ['is_valid_checksum' => true, 'processing_status' => DngWebhookEvent::STATUS_PROCESSED],
        ]))->toBe(Catalog::WEBHOOK_OK)
        ->and(Catalog::deriveWebhookState([
            ['is_valid_checksum' => true, 'processing_status' => DngWebhookEvent::STATUS_PROCESSED],
            ['is_valid_checksum' => true, 'processing_status' => DngWebhookEvent::STATUS_PENDING],
        ]))->toBe(Catalog::WEBHOOK_PENDING)
        ->and(Catalog::deriveWebhookState([
            ['is_valid_checksum' => true, 'processing_status' => DngWebhookEvent::STATUS_FAILED_RETRYABLE],
        ]))->toBe(Catalog::WEBHOOK_FAILED)
        ->and(Catalog::deriveWebhookState([
            ['is_valid_checksum' => true, 'processing_status' => DngWebhookEvent::STATUS_MISMATCH],
        ]))->toBe(Catalog::WEBHOOK_MISMATCH)
        ->and(Catalog::deriveWebhookState([
            ['is_valid_checksum' => false, 'processing_status' => DngWebhookEvent::STATUS_PROCESSED],
            ['is_valid_checksum' => true, 'processing_status' => DngWebhookEvent::STATUS_MISMATCH],
        ]))->toBe(Catalog::WEBHOOK_INVALID_CHECKSUM);
});

it('marks failed, mismatched, and invalid-checksum webhook states as problems', function () {
    expect(Catalog::isWebhookProblem(Catalog::WEBHOOK_FAILED))->toBeTrue()
        ->and(Catalog::isWebhookProblem(Catalog::WEBHOOK_MISMATCH))->toBeTrue()
        ->and(Catalog::isWebhookProblem(Catalog::WEBHOOK_INVALID_CHECKSUM))->toBeTrue()
        ->and(Catalog::isWebhookProblem(Catalog::WEBHOOK_OK))->toBeFalse()
        ->and(Catalog::isWebhookProblem(Catalog::WEBHOOK_PENDING))->toBeFalse();
});

it('derives payment bridge, invoice, and allocation states from status', function () {
    expect(Catalog::derivePaymentBridge(7))->toBe(Catalog::BRIDGE_BRIDGED)
        ->and(Catalog::derivePaymentBridge(null))->toBe(Catalog::BRIDGE_NOT_BRIDGED)
        ->and(Catalog::deriveInvoiceState(DngPaymentRequest::STATUS_PAID_UNINVOICED, null))->toBe(Catalog::INVOICE_UNINVOICED)
        ->and(Catalog::deriveInvoiceState(DngPaymentRequest::STATUS_PAID_INVOICED, null))->toBe(Catalog::INVOICE_INVOICED)
        ->and(Catalog::deriveInvoiceState(DngPaymentRequest::STATUS_PUSHED_TO_DNG, 'INV-1'))->toBe(Catalog::INVOICE_INVOICED)
        ->and(Catalog::deriveInvoiceState(DngPaymentRequest::STATUS_PENDING, null))->toBe(Catalog::INVOICE_NOT_APPLICABLE)
        ->and(Catalog::deriveAllocationState(DngPaymentRequest::STATUS_RECONCILED))->toBe(Catalog::ALLOCATION_RECONCILED)
        ->and(Catalog::deriveAllocationState(DngPaymentRequest::STATUS_PAID_UNINVOICED))->toBe(Catalog::ALLOCATION_PENDING)
        ->and(Catalog::deriveAllocationState(DngPaymentRequest::STATUS_PENDING))->toBe(Catalog::ALLOCATION_NOT_APPLICABLE);
});

it('classifies cancel/retry/error posture with cancelled outranking error', function () {
    expect(Catalog::deriveFlowState(DngPaymentRequest::STATUS_CANCELLED, true, true))->toBe(Catalog::FLOW_CANCELLED)
        ->and(Catalog::deriveFlowState(DngPaymentRequest::STATUS_FAILED, false, false))->toBe(Catalog::FLOW_ERROR)
        ->and(Catalog::deriveFlowState(DngPaymentRequest::STATUS_PUSHED_TO_DNG, true, false))->toBe(Catalog::FLOW_ERROR)
        ->and(Catalog::deriveFlowState(DngPaymentRequest::STATUS_PUSHED_TO_DNG, false, true))->toBe(Catalog::FLOW_RETRYING)
        ->and(Catalog::deriveFlowState(DngPaymentRequest::STATUS_PENDING, false, false))->toBe(Catalog::FLOW_NONE);
});

it('resolves related semester lineage into single, multi, and unknown states', function () {
    expect(Catalog::relatedSemesterState([]))->toBe(Catalog::SEMESTER_UNKNOWN)
        ->and(Catalog::relatedSemesterState([5, 5]))->toBe(Catalog::SEMESTER_SINGLE)
        ->and(Catalog::relatedSemesterState([5, 6]))->toBe(Catalog::SEMESTER_MULTI);
});

it('computes attention buckets including staleness and overdue push windows', function () {
    $now = Carbon::parse('2026-06-18 12:00:00');

    expect(Catalog::attentionBucketsFor(DngPaymentRequest::STATUS_FAILED, $now, null, Catalog::WEBHOOK_NONE, $now))
        ->toContain('failed_request')
        ->and(Catalog::attentionBucketsFor(DngPaymentRequest::STATUS_PUSHED_TO_DNG, $now, null, Catalog::WEBHOOK_MISMATCH, $now))
        ->toContain('webhook_problem')
        ->and(Catalog::attentionBucketsFor(DngPaymentRequest::STATUS_PAID_UNINVOICED, $now, null, Catalog::WEBHOOK_OK, $now))
        ->toContain('paid_uninvoiced');

    // pending older than 60 minutes is stale; a fresh pending is not.
    expect(Catalog::attentionBucketsFor(DngPaymentRequest::STATUS_PENDING, $now->copy()->subMinutes(61), null, Catalog::WEBHOOK_NONE, $now))
        ->toContain('pending_stale')
        ->and(Catalog::attentionBucketsFor(DngPaymentRequest::STATUS_PENDING, $now->copy()->subMinutes(10), null, Catalog::WEBHOOK_NONE, $now))
        ->not->toContain('pending_stale');

    // pushed_to_dng past its due date is overdue.
    expect(Catalog::attentionBucketsFor(DngPaymentRequest::STATUS_PUSHED_TO_DNG, $now, $now->copy()->subDay(), Catalog::WEBHOOK_OK, $now))
        ->toContain('overdue_pushed')
        ->and(Catalog::attentionBucketsFor(DngPaymentRequest::STATUS_PUSHED_TO_DNG, $now, $now->copy()->addDay(), Catalog::WEBHOOK_OK, $now))
        ->not->toContain('overdue_pushed');
});
