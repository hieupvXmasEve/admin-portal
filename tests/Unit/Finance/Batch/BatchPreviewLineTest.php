<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

it('exposes the key and the line hash', function () {
    $line = new BatchPreviewLine(
        key: 'charge:student:1:type:BHYT',
        hashPayload: ['student_id' => 1, 'charge_type' => 'BHYT', 'net' => 500000.0],
        display: ['label' => 'Nguyen Van A', 'diff' => 'create', 'net' => 500000.0],
    );

    expect($line->key)->toBe('charge:student:1:type:BHYT')
        ->and($line->hash())->toBeString()->toHaveLength(64);
});

it('maps a list of lines into a key=>hash token payload', function () {
    $lines = [
        new BatchPreviewLine('a', ['x' => 1], []),
        new BatchPreviewLine('b', ['x' => 2], []),
    ];

    $payload = BatchPreviewLine::toTokenPayload($lines);

    expect($payload)->toHaveKeys(['a', 'b'])
        ->and($payload['a'])->toHaveLength(64);
});

it('lists job types with their required action permissions', function () {
    expect(BatchJobType::ChargeGeneration->value)->toBe('charge_generation')
        ->and(BatchJobType::DngPush->value)->toBe('dng_push');
});
