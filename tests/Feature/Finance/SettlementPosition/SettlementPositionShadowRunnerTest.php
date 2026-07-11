<?php

declare(strict_types=1);

use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssueCatalog;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionShadowRunner;
use App\Shared\Contracts\Finance\SettlementPositionReader;

require_once __DIR__.'/CurrentPayableSettlementPositionReaderTest.php';

it('checks canonical raw evidence and canonical output at one snapshot without invoking legacy consumers', function (): void {
    [, $line, $invoice] = createPayableSettlementLine();
    applyPositionDiscount($invoice, $line, '200000.00');
    applyPositionCash($line, '300000.00');
    applyPositionCredit($line, '100000.00');

    $report = app(SettlementPositionShadowRunner::class)->run(
        SettlementPositionScope::payableLine((int) $line->id),
    );

    expect($report->target_identity)->toBe([
        'scope_type' => 'payable_line',
        'scope_id' => (int) $line->id,
        'payable_line_id' => (int) $line->id,
        'finance_obligation_id' => $report->position->finance_obligation_id,
        'invoice_id' => (int) $line->invoice_id,
        'billing_account_id' => $report->position->billing_account_id,
        'fee_type' => $report->position->fee_type,
    ])
        ->and($report->snapshot_version)->toBe($report->position->snapshot_version)
        ->and($report->captured_at)->toEqual($report->position->captured_at)
        ->and($report->raw_evidence->cash->amount)->toBe('300000.00')
        ->and($report->raw_evidence->credit->amount)->toBe('100000.00')
        ->and($report->status)->toBe('match')
        ->and($report->mismatches)->toBe([]);
});

it('reports canonical integrity findings as explained mismatches with invariant mappings', function (): void {
    [, $line] = createPayableSettlementLine();
    applyPositionCredit($line, '1200000.00');

    $report = app(SettlementPositionShadowRunner::class)->run(
        SettlementPositionScope::payableLine((int) $line->id),
    );

    expect($report->status)->toBe('explained_mismatch')
        ->and($report->hasIssue(SettlementPositionIssueCatalog::issueForInvariant('INV-8')))->toBeTrue()
        ->and($report->mismatches[0]->kind)->toBe('canonical_integrity_issue')
        ->and($report->mismatches[0]->explained)->toBeTrue()
        ->and($report->mismatches[0]->evidence['position_issue_code'])->toBe(SettlementPositionIssue::CREDIT_EXCEEDS_REMAINING)
        ->and($report->mismatches[0]->snapshot_version)->toBe($report->snapshot_version);
});

it('does not mutate settlement data while running a shadow check', function (): void {
    [, $line] = createPayableSettlementLine();
    $before = app(SettlementPositionReader::class)->forPayableLine((int) $line->id);

    app(SettlementPositionShadowRunner::class)->run(SettlementPositionScope::payableLine((int) $line->id));

    $after = app(SettlementPositionReader::class)->forPayableLine((int) $line->id);

    expect($after->raw_evidence->gross->amount)->toBe($before->raw_evidence->gross->amount)
        ->and($after->raw_evidence->cash->amount)->toBe($before->raw_evidence->cash->amount)
        ->and($after->raw_evidence->credit->amount)->toBe($before->raw_evidence->credit->amount);
});
