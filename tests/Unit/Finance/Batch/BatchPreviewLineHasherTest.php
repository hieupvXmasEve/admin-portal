<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Batch\BatchPreviewLineHasher;

it('hashes identical payloads identically regardless of key order', function () {
    $a = ['student_id' => 1, 'charge_type' => 'BHYT', 'net' => 500000.0];
    $b = ['net' => 500000.0, 'charge_type' => 'BHYT', 'student_id' => 1];

    expect(BatchPreviewLineHasher::hashLine($a))->toBe(BatchPreviewLineHasher::hashLine($b));
});

it('changes the hash when any resolved value changes', function () {
    $base = ['student_id' => 1, 'charge_type' => 'BHYT', 'net' => 500000.0];
    $changed = ['student_id' => 1, 'charge_type' => 'BHYT', 'net' => 500001.0];

    expect(BatchPreviewLineHasher::hashLine($base))->not->toBe(BatchPreviewLineHasher::hashLine($changed));
});

it('hashes nested arrays deterministically (warning_codes order independent)', function () {
    $a = ['student_id' => 1, 'warning_codes' => ['scholarship_expiring', 'late_enroll']];
    $b = ['student_id' => 1, 'warning_codes' => ['late_enroll', 'scholarship_expiring']];

    expect(BatchPreviewLineHasher::hashLine($a))->toBe(BatchPreviewLineHasher::hashLine($b));
});

it('builds a fee-config fingerprint from id + updated_at, not a version column', function () {
    $fp = BatchPreviewLineHasher::feeConfigFingerprint(42, '2026-06-01 10:00:00', 7, '2026-06-02 11:00:00');
    expect($fp)->toBe('plan:42@2026-06-01 10:00:00|term:7@2026-06-02 11:00:00');
});

it('returns a stable "none" fingerprint when there is no fee config', function () {
    expect(BatchPreviewLineHasher::feeConfigFingerprint(null, null, null, null))->toBe('none');
});