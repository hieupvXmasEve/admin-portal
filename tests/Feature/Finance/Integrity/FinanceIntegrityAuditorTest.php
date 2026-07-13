<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
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

it('allows multiple invoices for one student and semester', function (): void {
    $student = auditStudent();
    $semester = Semester::factory()->create();

    foreach (['INV-MULTI-A', 'INV-MULTI-B'] as $invoiceNumber) {
        StudentInvoice::query()->create([
            'invoice_number' => $invoiceNumber,
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'status' => 'draft',
            'due_date' => now()->addDays(30),
        ]);
    }

    $invariant = app(FinanceInvariantRegistry::class)->find('INV-6');

    expect(app(FinanceIntegrityAuditor::class)->count($invariant))->toBe(0);
});

it('flags an invoice line whose charge belongs to another student or semester', function (): void {
    $invoiceStudent = auditStudent();
    $chargeStudent = auditStudent();
    $invoiceSemester = Semester::factory()->create();
    $chargeSemester = Semester::factory()->create();
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-SCOPE-MISMATCH',
        'student_id' => $invoiceStudent->id,
        'semester_id' => $invoiceSemester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    $charge = FinanceCharge::query()->create([
        'student_id' => $chargeStudent->id,
        'semester_id' => $chargeSemester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1000,
        'description' => 'Mismatched audit fixture',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 1000,
        'description_snapshot' => 'Mismatched audit fixture',
        'status' => 'active',
    ]);

    $invariant = app(FinanceInvariantRegistry::class)->find('INV-17');
    $auditor = app(FinanceIntegrityAuditor::class);

    expect($auditor->count($invariant))->toBe(1)
        ->and($auditor->samples($invariant))->toBe([$invoice->id]);
});

it('flags an active invoice line whose charge is void', function (): void {
    $student = auditStudent();
    $semester = Semester::factory()->create();
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-ACTIVE-LINE-VOID-CHARGE',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1000,
        'description' => 'Voided charge with stale active line',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_VOID,
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 1000,
        'description_snapshot' => 'Voided charge with stale active line',
        'status' => 'active',
    ]);

    $invariant = app(FinanceInvariantRegistry::class)->find('INV-18');
    $auditor = app(FinanceIntegrityAuditor::class);

    expect($auditor->count($invariant))->toBe(1)
        ->and($auditor->samples($invariant))->toBe([$invoice->id]);
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

    expect(app(FinanceIntegrityAuditor::class)->findForScope(new FinanceAuditScope))->toBe([]);
});

it('runs every one of the 18 invariants cleanly, globally and scoped (no SQL errors)', function () {
    // Guards all 18 ported SQL splices: a bad column or mis-spliced {scope}/{ids}
    // would throw here, catching regressions the token-only registry test cannot.
    $student = auditStudent();
    $registry = app(FinanceInvariantRegistry::class);
    $auditor = app(FinanceIntegrityAuditor::class);
    $scope = new FinanceAuditScope(studentIds: [$student->id]);

    foreach ($registry->all() as $invariant) {
        expect($auditor->count($invariant, null))->toBeInt()
            ->and($auditor->count($invariant, $scope))->toBeInt()
            ->and($auditor->samples($invariant, $scope))->toBeArray();
    }
});

it('runs every invariant cleanly when scope combines students with a semester', function () {
    $student = auditStudent();
    $semester = Semester::factory()->create();
    $registry = app(FinanceInvariantRegistry::class);
    $auditor = app(FinanceIntegrityAuditor::class);
    $scope = new FinanceAuditScope(studentIds: [$student->id], semesterId: $semester->id);

    foreach ($registry->all() as $invariant) {
        expect($auditor->count($invariant, $scope))->toBeInt()
            ->and($auditor->samples($invariant, $scope))->toBeArray();
    }
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
