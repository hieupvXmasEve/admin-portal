<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Audit\FinanceLedgerTimelineBuilder;

it('preserves signed reversal entries and orders by time', function () {
    $graph = [
        'ledger_entries' => [
            ['at' => '2026-01-02 10:00:00', 'type' => 'payment_application', 'amount' => -50.0, 'entry_type' => 'reversal', 'refs' => ['payment' => 1, 'invoice_line' => 9]],
            ['at' => '2026-01-01 09:00:00', 'type' => 'payment_application', 'amount' => 200.0, 'entry_type' => 'application', 'refs' => ['payment' => 1, 'invoice_line' => 9]],
        ],
    ];

    $timeline = (new FinanceLedgerTimelineBuilder())->build($graph);

    expect($timeline[0]['at'])->toBe('2026-01-01 09:00:00')
        ->and($timeline[0]['signed_amount'])->toBe(200.0)
        ->and($timeline[1]['signed_amount'])->toBe(-50.0)
        ->and($timeline[1]['label'])->toContain('reversal')
        ->and($timeline[0]['refs'])->toBe(['payment' => 1, 'invoice_line' => 9]);
});

it('returns an empty list when there are no ledger entries', function () {
    expect((new FinanceLedgerTimelineBuilder())->build([]))->toBe([])
        ->and((new FinanceLedgerTimelineBuilder())->build(['ledger_entries' => []]))->toBe([]);
});

it('orders null-dated entries after dated ones', function () {
    $graph = [
        'ledger_entries' => [
            ['at' => null, 'type' => 'discount_allocation', 'amount' => 10.0, 'entry_type' => 'allocation', 'refs' => []],
            ['at' => '2026-01-01 00:00:00', 'type' => 'payment_application', 'amount' => 5.0, 'entry_type' => 'application', 'refs' => []],
        ],
    ];

    $timeline = (new FinanceLedgerTimelineBuilder())->build($graph);

    expect($timeline[0]['at'])->toBe('2026-01-01 00:00:00')
        ->and($timeline[1]['at'])->toBeNull();
});
