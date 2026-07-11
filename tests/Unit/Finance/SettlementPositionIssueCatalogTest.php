<?php

declare(strict_types=1);

use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssueCatalog;

it('maps every Finance invariant into a stable Settlement Position issue code', function (): void {
    expect(SettlementPositionIssueCatalog::invariantIssues())->toHaveCount(16)
        ->and(SettlementPositionIssueCatalog::issueForInvariant('INV-1'))
        ->toBe('settlement_position.finance_invariant.payment_over_allocated')
        ->and(SettlementPositionIssueCatalog::issueForInvariant('INV-16'))
        ->toBe('settlement_position.finance_invariant.active_signed_adjustment');
});

it('maps raw balance issues back to the credit-aware invariant', function (): void {
    expect(SettlementPositionIssueCatalog::invariantForIssue(SettlementPositionIssue::CREDIT_EXCEEDS_REMAINING))
        ->toBe('INV-8')
        ->and(SettlementPositionIssueCatalog::invariantForIssue(SettlementPositionIssue::NEGATIVE_RAW_REMAINING))
        ->toBe('INV-8');
});
