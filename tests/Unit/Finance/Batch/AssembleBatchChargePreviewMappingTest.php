<?php

declare(strict_types=1);

use App\Modules\Finance\Queries\Batch\AssembleBatchChargePreviewQuery;

it('maps an eligible row into a 🟢 create line with a stable key and net in the hash', function () {
    $row = [
        'id' => 11,
        'student_id' => 'SV011',
        'full_name' => 'Nguyen Van A',
        'estimated_amount' => 500000.0,
        'gross_amount' => 600000.0,
        'discount_amount' => 100000.0,
        'scholarship_id' => 7,
        'scholarship_name' => 'Học bổng Tài năng',
        'scholarship_type' => 'percentage',
        'scholarship_raw_value' => 20,
        'scholarship_amount' => 100000.0,
        'voucher_codes' => [],
        'voucher_amount' => 0.0,
        'voucher_id' => null,
        'has_existing_charge' => false,
        'warning' => null,
        'fee_plan_id' => 42,
        'fee_plan_updated_at' => '2026-06-01 10:00:00',
        'fee_term_id' => 9,
        'fee_term_updated_at' => '2026-06-02 11:00:00',
    ];

    $line = AssembleBatchChargePreviewQuery::mapChargeRow($row, 'major', 5, 'create');

    expect($line->key)->toBe('charge:major:student:11:semester:5')
        ->and($line->display['diff'])->toBe('create')
        ->and($line->display['net'])->toBe(500000.0)
        ->and($line->display['scholarship_name'])->toBe('Học bổng Tài năng')
        ->and($line->display['scholarship_type'])->toBe('percentage')
        ->and($line->display['scholarship_amount'])->toBe(100000.0)
        ->and($line->hashPayload['net'])->toBe(500000.0)
        ->and($line->hashPayload['scholarship_id'])->toBe(7)
        ->and($line->hashPayload['fee_config_fingerprint'])->toBe('plan:42@2026-06-01 10:00:00|term:9@2026-06-02 11:00:00');
});

it('maps a warning row into a 🟠 warning line carrying the reason', function () {
    $row = [
        'id' => 12, 'student_id' => 'SV012', 'full_name' => 'B',
        'estimated_amount' => 0.0, 'has_existing_charge' => false,
        'warning' => 'scholarship_expired',
    ];

    $line = AssembleBatchChargePreviewQuery::mapChargeRow($row, 'major', 5, 'warning');

    expect($line->display['diff'])->toBe('warning')
        ->and($line->display['reason'])->toBe('scholarship_expired')
        ->and($line->hashPayload['warning_codes'])->toBe(['scholarship_expired']);
});

it('maps an already-charged row into a ⚪ skip line with a reason', function () {
    $row = [
        'id' => 13, 'student_id' => 'SV013', 'full_name' => 'C',
        'estimated_amount' => 500000.0, 'has_existing_charge' => true, 'warning' => null,
    ];

    $line = AssembleBatchChargePreviewQuery::mapChargeRow($row, 'non_academic', 5, 'skip');

    expect($line->display['diff'])->toBe('skip')
        ->and($line->display['reason'])->toBe('already_charged');
});

it('maps EGC chargeable levels into preview amount and block metadata', function () {
    $row = [
        'student_id' => 14,
        'student_code' => 'EGC014',
        'student_name' => 'EGC Student',
        'max_chargeable_blocks' => 2,
        'chargeable_levels' => [
            ['level_number' => 1, 'amount' => 12000000],
            ['level_number' => 2, 'amount' => 15000000],
        ],
    ];

    $line = AssembleBatchChargePreviewQuery::mapChargeRow($row, 'egc', 5, 'create');

    expect($line->key)->toBe('charge:egc:student:14:semester:5')
        ->and($line->display['net'])->toBe(27000000.0)
        ->and($line->display['block_count'])->toBe(2)
        ->and($line->display['block_amounts'])->toBe([12000000.0, 15000000.0])
        ->and($line->hashPayload['net'])->toBe(27000000.0);
});
