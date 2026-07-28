<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Services\DngChecksumService;
use App\Modules\Finance\Dng\Services\DngClient;

/**
 * DNG recomputes the checksum from the Amount field it receives. Whatever the
 * client signs must therefore be exactly what the client sends — otherwise the
 * provider answers `CheckSum không chính xác` and the push is lost.
 *
 * The installment push path reads a decimal-cast attribute, so it hands the
 * client "17780000.00" while the payload transmits 17780000. That divergence is
 * what this file guards against.
 */
function dngClientForChecksumTest(): array
{
    config([
        'services.dng.hash_key' => 'test-hash-key-for-checksum-assertions',
        'services.dng.access_code' => 'TEST_ACCESS',
        'services.dng.api_code' => 'TEST_API',
        'services.dng.client_code' => 'TEST_CLIENT',
        'services.dng.login' => 'TEST_LOGIN',
        'services.dng.base_url' => 'https://dng.test',
    ]);

    $checksum = new DngChecksumService;

    return [new DngClient($checksum), $checksum];
}

/**
 * @param  array<string, mixed>  $payload
 */
function expectedInsertChecksum(DngChecksumService $service, array $payload): string
{
    return $service->generate(
        'TEST_ACCESS'
        .'TEST_API'
        .$payload['CampusCode']
        .(string) $payload['Amount']
        .$payload['ItemId']
        .mb_strtolower((string) $payload['StudentId'], 'UTF-8')
    );
}

/**
 * @return array<string, mixed>
 */
function insertRecordData(int|float|string $amount): array
{
    return [
        'student_code' => 'MUH110987',
        'campus_code' => 'FMEHN',
        'type' => 'HP',
        'amount' => $amount,
        'item_id' => 'swinx-rsv-04339872d05d9e26a3e5d2f2cef1cec8',
        'student_name' => 'NGUYEN HOANG NGUYEN MINH',
        'email' => 'student@example.test',
        'estimate_time' => '07/26',
        'student_address' => 'Ha Noi',
    ];
}

it('signs the same Amount it transmits when the caller passes a decimal string', function () {
    [$client, $checksum] = dngClientForChecksumTest();

    // What FinanceChargeInstallment's decimal:2 cast produces.
    $payload = $client->buildInsertNewRecordPayload(insertRecordData('17780000.00'));

    expect($payload['Amount'])->toEqual(17780000)
        ->and($payload['CheckSum'])->toBe(expectedInsertChecksum($checksum, $payload));
});

it('signs the same Amount it transmits for integer, float and cancellation amounts', function (int|float|string $amount) {
    [$client, $checksum] = dngClientForChecksumTest();

    $payload = $client->buildInsertNewRecordPayload(insertRecordData($amount));

    expect($payload['CheckSum'])->toBe(expectedInsertChecksum($checksum, $payload));
})->with([
    'integer' => 17_780_000,
    'float with cents' => 17_780_000.5,
    'plain numeric string' => '17780000',
    'decimal string with cents' => '17780000.50',
    'cancellation sentinel' => -1,
]);

it('keeps a decimal string and its integer form on the same checksum', function () {
    [$client] = dngClientForChecksumTest();

    $fromDecimalCast = $client->buildInsertNewRecordPayload(insertRecordData('17780000.00'));
    $fromInteger = $client->buildInsertNewRecordPayload(insertRecordData(17_780_000));

    expect($fromDecimalCast['CheckSum'])->toBe($fromInteger['CheckSum']);
});
