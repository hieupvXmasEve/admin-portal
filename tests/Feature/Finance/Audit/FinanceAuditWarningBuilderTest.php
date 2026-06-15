<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Audit\FinanceAuditWarningBuilder;
use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

require_once __DIR__.'/audit_fixtures.php';
require_once __DIR__.'/../Integrity/integrity_fixtures.php';

it('emits a cache_drift warning when cached_paid_amount disagrees with SettlementService', function () {
    [$student, $invoice] = seedStaleCacheFixture();

    $warnings = app(FinanceAuditWarningBuilder::class)
        ->build(new FinanceAuditScope(studentIds: [$student->id]));

    expect(collect($warnings)->where('kind', 'cache_drift')->pluck('sample_ids')->flatten())
        ->toContain($invoice->id);
});

it('emits invariant warnings only for the scoped student', function () {
    $bad = auditStudent();
    seedOverAllocatedPayment($bad);

    $warnings = app(FinanceAuditWarningBuilder::class)
        ->build(new FinanceAuditScope(studentIds: [$bad->id]));

    expect(collect($warnings)->where('kind', 'invariant')->pluck('code'))->toContain('INV-1');
});

it('returns nothing for an empty scope', function () {
    expect(app(FinanceAuditWarningBuilder::class)->build(new FinanceAuditScope()))->toBe([]);
});
