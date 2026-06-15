<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use App\Modules\Finance\Support\Integrity\FinanceInvariant;
use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

require_once __DIR__.'/integrity_fixtures.php';

it('summarize() global counts INV-1 over-allocation', function () {
    $student = auditStudent();
    seedOverAllocatedPayment($student);

    $summary = collect(app(FinanceIntegrityAuditor::class)->summarize(null))
        ->keyBy('code');

    expect($summary['INV-1']['count'])->toBe(1)
        ->and($summary['INV-1']['error'])->toBeNull();
});

it('findForScope() returns INV-1 only for the offending student', function () {
    $bad = auditStudent();
    $good = auditStudent();
    seedOverAllocatedPayment($bad);

    $findingsBad = app(FinanceIntegrityAuditor::class)
        ->findForScope(new FinanceAuditScope(studentIds: [$bad->id]));
    $findingsGood = app(FinanceIntegrityAuditor::class)
        ->findForScope(new FinanceAuditScope(studentIds: [$good->id]));

    expect(collect($findingsBad)->pluck('code'))->toContain('INV-1')
        ->and(collect($findingsGood)->pluck('code'))->not->toContain('INV-1');
});

it('findForScope() returns nothing for an empty scope rather than scanning globally', function () {
    $student = auditStudent();
    seedOverAllocatedPayment($student);

    expect(app(FinanceIntegrityAuditor::class)->findForScope(new FinanceAuditScope()))->toBe([]);
});

it('surfaces a failing invariant as an error, never a false clean result', function () {
    // Registry whose single invariant points at a non-existent table.
    $broken = new class extends FinanceInvariantRegistry
    {
        public function all(): array
        {
            return [new FinanceInvariant(
                'INV-1', 'CRITICAL', 'broken',
                'SELECT COUNT(*) c FROM nonexistent_audit_table WHERE {scope}',
                'SELECT id FROM nonexistent_audit_table WHERE {scope} LIMIT 5',
                'student_id IN ({ids})',
            )];
        }
    };
    $auditor = new FinanceIntegrityAuditor($broken);

    $summary = collect($auditor->summarize(null))->keyBy('code');
    expect($summary['INV-1']['count'])->toBeNull()
        ->and($summary['INV-1']['error'])->not->toBeNull();

    $findings = $auditor->findForScope(new FinanceAuditScope(studentIds: [1]));
    expect(collect($findings)->firstWhere('code', 'INV-1')['kind'])->toBe('invariant_error');
});
