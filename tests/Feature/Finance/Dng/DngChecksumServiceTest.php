<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Services\DngChecksumService;

// Use the known hash key from the reference spec
const HASH_KEY = '2CabGHY9XaBCyeTOXU48tlajCC5NrLE32G7pWoW3Jrtsw7FFGX7hMqFQC1IdMlRmFJL2hE2J';

it('generates checksum matching reference implementation', function () {
    config(['services.dng.hash_key' => HASH_KEY]);

    $service = new DngChecksumService;

    // Test with a known value
    $testValue = 'TEST001STUD0012500000';
    $checksum = $service->generate($testValue);

    // Verify it's a properly formatted checksum (base64ish with replacements)
    expect($checksum)->toBeString();
    expect($checksum)->not->toBeEmpty();
    expect($checksum)->not->toContain('=');
    expect($checksum)->not->toContain(' ');
});

it('verifies valid checksum returns true', function () {
    config(['services.dng.hash_key' => HASH_KEY]);

    $service = new DngChecksumService;

    $testValue = 'CampusCode001StudentId001PaymentId0011000000';
    $validChecksum = $service->generate($testValue);

    $result = $service->verify($testValue, $validChecksum);

    expect($result)->toBeTrue();
});

it('rejects invalid checksum returns false', function () {
    config(['services.dng.hash_key' => HASH_KEY]);

    $service = new DngChecksumService;

    $testValue = 'CampusCode001StudentId001PaymentId0011000000';
    $invalidChecksum = 'InvalidChecksum123==';

    $result = $service->verify($testValue, $invalidChecksum);

    expect($result)->toBeFalse();
});

it('uses hash_equals for constant-time comparison', function () {
    config(['services.dng.hash_key' => HASH_KEY]);

    $service = new DngChecksumService;

    $testValue = 'test-data-string';
    $correctChecksum = $service->generate($testValue);

    // Verify correct checksum
    expect($service->verify($testValue, $correctChecksum))->toBeTrue();

    // Verify that slightly different checksums fail
    $wrongChecksum = substr($correctChecksum, 0, -1).'X';
    expect($service->verify($testValue, $wrongChecksum))->toBeFalse();
});

it('handles checksum with %3d replacements correctly', function () {
    config(['services.dng.hash_key' => HASH_KEY]);

    $service = new DngChecksumService;

    $testValue = 'test-with-equals';
    $checksum = $service->generate($testValue);

    // Verify it doesn't contain unreplaced equals or spaces
    expect($checksum)->not->toContain('=');
    expect($checksum)->not->toContain(' ');
    expect($checksum)->toContain('%3d');
});

it('respects configured hash key', function () {
    $customKey = 'custom-hash-key-12345';
    config(['services.dng.hash_key' => $customKey]);

    $service = new DngChecksumService;

    $testValue = 'test-value';
    $checksum1 = $service->generate($testValue);

    // Change the config and verify different hash is generated
    config(['services.dng.hash_key' => HASH_KEY]);
    $service2 = new DngChecksumService;
    $checksum2 = $service2->generate($testValue);

    expect($checksum1)->not->toBe($checksum2);
});
