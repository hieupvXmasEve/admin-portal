<?php

declare(strict_types=1);

use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Modules\Finance\Support\SettlementPosition\MoneyItemStatusContext;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionAmounts;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionRawEvidence;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistPresenter;
use Carbon\CarbonImmutable;

function moneyItemPosition(string $state, bool $valid, float $remaining, float $cash = 0.0): SettlementPosition
{
    if (! $valid) {
        return SettlementPosition::invalid(
            SettlementPosition::SCOPE_PAYABLE_LINE,
            1,
            1,
            null,
            SettlementPositionRawEvidence::empty(),
            [],
        );
    }

    $amounts = new SettlementPositionAmounts(
        Money::vnd((int) 10000000),
        Money::vnd(0),
        Money::vnd((int) $cash),
        Money::vnd(0),
        Money::vnd((int) $remaining),
    );

    return new SettlementPosition(
        scope_type: SettlementPosition::SCOPE_PAYABLE_LINE,
        scope_id: 1,
        payable_line_id: 1,
        finance_obligation_id: null,
        invoice_id: 1,
        billing_account_id: 1,
        fee_type: 'tuition_term',
        position_mode: SettlementPosition::MODE_CURRENT,
        captured_at: CarbonImmutable::now(),
        snapshot_version: 'test',
        settlement_state: $state,
        valid: true,
        raw_evidence: SettlementPositionRawEvidence::empty(),
        amounts: $amounts,
        issues: [],
    );
}

it('derives money_item_status from the truth table without contradicting remaining', function (array $input, string $expected): void {
    $position = moneyItemPosition($input['state'], $input['valid'], $input['remaining'], $input['cash'] ?? 0);
    $context = new MoneyItemStatusContext(
        position: $position,
        dngHoldingThisItem: $input['dng_holding'] ?? false,
        dngNeedsReview: $input['dng_review'] ?? false,
        obligationCancelled: $input['cancelled'] ?? false,
        chargeVoid: $input['void'] ?? false,
        dueDate: $input['due'] ?? null,
    );
    $status = app(SettlementPositionWorklistPresenter::class)->moneyItemStatus($context);
    $summary = app(SettlementPositionWorklistPresenter::class)->summarize($position, false, $context);

    expect($status['code'])->toBe($expected)
        ->and($summary['money_item_status']['code'])->toBe($expected);

    if ($expected === MoneyItemStatusContext::COMPLETED) {
        expect($context->position->amounts === null ? 0.0 : (float) $context->position->amounts->remaining->amount)->toBeLessThanOrEqual(0.0);
    }
    if ($expected === MoneyItemStatusContext::OVERDUE) {
        expect($input['due'])->not->toBeNull()
            ->and($input['remaining'])->toBeGreaterThan(0);
    }
    if ($status['hide_amounts']) {
        expect($expected)->toBe(MoneyItemStatusContext::REVIEWING);
    }
})->with([
    'invalid position' => [['state' => SettlementPosition::STATE_INVALID, 'valid' => false, 'remaining' => 100], MoneyItemStatusContext::REVIEWING],
    'missing position' => [['state' => SettlementPosition::STATE_MISSING, 'valid' => false, 'remaining' => 0], MoneyItemStatusContext::REVIEWING],
    'dng needs review hides amounts' => [['state' => SettlementPosition::STATE_UNPAID, 'valid' => true, 'remaining' => 10000000, 'dng_review' => true], MoneyItemStatusContext::REVIEWING],
    'voided charge' => [['state' => SettlementPosition::STATE_UNPAID, 'valid' => true, 'remaining' => 10000000, 'void' => true], MoneyItemStatusContext::ADJUSTED],
    'cancelled obligation' => [['state' => SettlementPosition::STATE_UNPAID, 'valid' => true, 'remaining' => 0, 'cancelled' => true], MoneyItemStatusContext::ADJUSTED],
    'settled by cash' => [['state' => SettlementPosition::STATE_SETTLED_BY_CASH, 'valid' => true, 'remaining' => 0, 'cash' => 10000000], MoneyItemStatusContext::COMPLETED],
    'settled by reduction' => [['state' => SettlementPosition::STATE_SETTLED_BY_REDUCTION, 'valid' => true, 'remaining' => 0, 'cash' => 0], MoneyItemStatusContext::COMPLETED],
    'dng holding beats overdue' => [['state' => SettlementPosition::STATE_UNPAID, 'valid' => true, 'remaining' => 5000000, 'dng_holding' => true, 'due' => CarbonImmutable::now()->subDay()], MoneyItemStatusContext::PROCESSING],
    'past due without dng' => [['state' => SettlementPosition::STATE_UNPAID, 'valid' => true, 'remaining' => 5000000, 'due' => CarbonImmutable::now()->subDay()], MoneyItemStatusContext::OVERDUE],
    'no due date is never overdue' => [['state' => SettlementPosition::STATE_UNPAID, 'valid' => true, 'remaining' => 5000000, 'due' => null], MoneyItemStatusContext::AWAITING_PAYMENT],
    'future due awaits payment' => [['state' => SettlementPosition::STATE_UNPAID, 'valid' => true, 'remaining' => 5000000, 'due' => CarbonImmutable::now()->addDay()], MoneyItemStatusContext::AWAITING_PAYMENT],
    'unapplied cash does not flip other items' => [['state' => SettlementPosition::STATE_UNPAID, 'valid' => true, 'remaining' => 5000000], MoneyItemStatusContext::AWAITING_PAYMENT],
]);
