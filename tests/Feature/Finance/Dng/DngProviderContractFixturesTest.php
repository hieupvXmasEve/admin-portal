<?php

declare(strict_types=1);

it('keeps every DNG provider-contract fixture correlatable and sanitized', function () {
    $fixtureDirectory = base_path('tests/Fixtures/Finance/DngProviderContract');

    $fixtures = [
        'insert-new-record.json' => ['request.ApiCode', 'request.StudentId', 'request.CampusCode', 'request.Type', 'request.Amount', 'request.ItemId', 'response.Code', 'response.data.TransactionID', 'response.data.PaymentId'],
        'retry-same-reservation.json' => ['first_request.ItemId', 'retry_request.ItemId', 'first_request.CampusCode', 'retry_request.CampusCode'],
        'cancel-record.json' => ['request.StudentId', 'request.CampusCode', 'request.Type', 'request.Amount', 'request.ItemId', 'response.Code'],
        'callback-payment-succeeded.json' => ['payload.StudentId', 'payload.CampusCode', 'payload.ItemId', 'payload.FeeType', 'payload.Amount', 'payload.PaymentId'],
        'daily-reconciliation-payment.json' => ['response.data.0.StudentId', 'response.data.0.CampusCode', 'response.data.0.ItemId', 'response.data.0.FeeType', 'response.data.0.Amount', 'response.data.0.PaymentId'],
        'payment-after-cancellation.json' => ['cancellation.request.ItemId', 'paid_callback.payload.ItemId', 'paid_callback.payload.PaymentId', 'required_local_outcome'],
        'pay-all-virtual-account.json' => ['request.StudentCode', 'request.CampusCode', 'request.FeeTypes.0', 'request.FeeTypes.1', 'local_request_map.0.item_id', 'local_request_map.1.item_id', 'response.Code'],
    ];

    foreach ($fixtures as $file => $requiredPaths) {
        $contents = file_get_contents($fixtureDirectory.'/'.$file);

        expect($contents)->not->toBeFalse();

        $fixture = json_decode((string) $contents, true, flags: JSON_THROW_ON_ERROR);

        expect($fixture['sanitized'] ?? null)->toBeTrue()
            ->and($fixture['provider_evidence_status'] ?? null)->toBeIn(['local_schema_only', 'confirmation_required']);

        foreach ($requiredPaths as $requiredPath) {
            expect(data_get($fixture, $requiredPath))->not->toBeNull();
        }

        expect($contents)
            ->not->toMatch('/@fpt\\.edu\\.vn|\\b0{12}\\b|DNG_ACCESS_CODE|DNG_HASH_KEY|access_code|hash_key/i');
    }

    $retryFixture = json_decode((string) file_get_contents($fixtureDirectory.'/retry-same-reservation.json'), true, flags: JSON_THROW_ON_ERROR);
    expect(data_get($retryFixture, 'first_request.ItemId'))
        ->toBe(data_get($retryFixture, 'retry_request.ItemId'));

    $latePaymentFixture = json_decode((string) file_get_contents($fixtureDirectory.'/payment-after-cancellation.json'), true, flags: JSON_THROW_ON_ERROR);
    expect(data_get($latePaymentFixture, 'cancellation.request.ItemId'))
        ->toBe(data_get($latePaymentFixture, 'paid_callback.payload.ItemId'));
});
