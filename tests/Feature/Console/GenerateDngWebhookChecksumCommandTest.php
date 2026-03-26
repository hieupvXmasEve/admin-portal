<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Services\DngChecksumService;

const TEST_HASH_KEY = '2CabGHY9XaBCyeTOXU48tlajCC5NrLE32G7pWoW3Jrtsw7FFGX7hMqFQC1IdMlRmFJL2hE2J';
const TEST_ACCESS_CODE = 'TEST_ACCESS';
const TEST_CLIENT_CODE = 'TEST_CLIENT';

beforeEach(function () {
    config([
        'services.dng.hash_key' => TEST_HASH_KEY,
        'services.dng.access_code' => TEST_ACCESS_CODE,
        'services.dng.client_code' => TEST_CLIENT_CODE,
    ]);
});

it('generates the expected DNG webhook checksum', function () {
    $checksumService = app(DngChecksumService::class);
    $expectedValue = TEST_ACCESS_CODE.TEST_CLIENT_CODE.'5000000.00'.'INV-001'.'STU001'.'tuition'.'CAMPUS001';
    $expectedChecksum = $checksumService->generate($expectedValue);

    $this->artisan('dng:webhook-checksum', [
        'amount' => '5000000.00',
        'student_id' => 'STU001',
        'fee_type' => 'tuition',
        'campus_code' => 'CAMPUS001',
        '--invoice-serial-number' => 'INV-001',
        '--only-checksum' => true,
    ])
        ->expectsOutput($expectedChecksum)
        ->assertSuccessful();
});

it('shows the checksum payload breakdown when only-checksum is not used', function () {
    $checksumService = app(DngChecksumService::class);
    $checksumValue = TEST_ACCESS_CODE.TEST_CLIENT_CODE.'5000000.00'.'STU001'.'tuition'.'CAMPUS001';

    $this->artisan('dng:webhook-checksum', [
        'amount' => '5000000.00',
        'student_id' => 'STU001',
        'fee_type' => 'tuition',
        'campus_code' => 'CAMPUS001',
    ])
        ->expectsTable(
            ['Field', 'Value'],
            [
                ['amount', '5000000.00'],
                ['student_id', 'STU001'],
                ['fee_type', 'tuition'],
                ['campus_code', 'CAMPUS001'],
                ['invoice_serial_number', ''],
                ['checksum_value', $checksumValue],
                ['checksum', $checksumService->generate($checksumValue)],
            ],
        )
        ->assertSuccessful();
});
