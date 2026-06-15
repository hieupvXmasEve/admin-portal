# Finance Audit Workspace (S-009 / FIN-REV-009) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only, permission-safe Finance Audit Workspace with universal search that resolves any finance identifier to a student-scoped money **graph + signed ledger timeline + integrity warnings**, all derived from existing canonical sources (no new money math).

**Architecture:** Backend-first. (1) Extract the 15 invariants currently locked inside `AuditFinanceInvariants` command into a shared, subject-scopeable `FinanceInvariantRegistry` + `FinanceIntegrityAuditor` (command output must stay byte-identical). (2) Build a CQRS read layer under `app/Modules/Finance/Queries/Audit/` that composes existing queries/`SettlementService`. (3) One Inertia page consuming deferred props. Warnings are a student-scoped projection of the shared invariants; derived balances and cache-drift come from `SettlementService`.

**Tech Stack:** Laravel 13 (PHP 8, `declare(strict_types=1)`), Pest 4, Inertia v3 + Vue 3 `<script setup lang="ts">`, Tailwind, lucide icons. Tests/commands run via `./scripts/dev.sh`.

---

## Conventions used by every task

- **Run commands via Docker:** `./scripts/dev.sh test --filter=...`, `./scripts/dev.sh artisan ...`, `./scripts/dev.sh npm run ...`. Never call bare `php`/`npm`.
- **Query objects:** namespace `App\Modules\Finance\Queries\...`, `declare(strict_types=1)`, single `handle(...)` returning `array`. Pattern reference: `app/Modules/Finance/Queries/GetPaymentDetailsQuery.php`.
- **Pure builders/support:** namespace `App\Modules\Finance\Support\...` (no `Query` suffix). Reference: `app/Modules/Finance/Support/BillingExceptionCollector.php`.
- **Campus scope:** read current campus as `app('campus')?->id`. Reference: `ListBillingExceptionsQuery`.
- **Permissions (config-driven):** add codes to `config/permission.php` under `access`, then `./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder`. There is no per-feature permission file.
- **Tests grant permissions** by mocking `App\Services\PermissionService::getUserPermissions` to return an array of codes (see `tests/Feature/Finance/DngAdminPagesTest.php:37-45`). Tests bind campus via `app()->singleton('campus', fn () => $campus)` and `session(['current_campus_id' => $campus->id])`.
- **Money** is `decimal:2` cast → compare with a `0.01` tolerance, never `===`.
- **Canonical shapes** (keep names identical across all tasks):
  - **Resolution:** `['status' => 'empty'|'single'|'ambiguous', 'target' => ['type' => string, 'id' => int]|null, 'matches' => list<['type' => string, 'id' => int, 'label' => string, 'sublabel' => string]>]`. `type` ∈ `student|invoice|payment|charge|dng`.
  - **Graph node:** `['key' => "{type}:{id}", 'type' => string, 'id' => int, 'label' => string, 'status' => ?string, 'amount' => ?float, 'date' => ?string]`.
  - **Graph edge:** `['from' => string, 'to' => string, 'kind' => string, 'amount' => ?float]`.
  - **Timeline event:** `['at' => ?string, 'type' => string, 'signed_amount' => float, 'label' => string, 'refs' => array<string,int>]`.
  - **Warning:** `['code' => string, 'severity' => string, 'label' => string, 'kind' => 'invariant'|'cache_drift', 'sample_ids' => list<int>]`.

---

## Task 0: Establish a runnable test baseline in the worktree

**Why:** Worktrees do not share `vendor/`/`node_modules/`, tests run in Docker, and `tests/Feature/Finance` has a **known pre-existing baseline of 26 fail / 324 pass** (memory, 2026-06-14). Capture the baseline so new failures are distinguishable.

**Files:** none (environment only).

- [ ] **Step 1: Determine how Docker mounts the project**

Run: `sed -n '1,80p' scripts/dev.sh` and `cat docker/docker-compose*.yml 2>/dev/null || cat docker-compose*.yml`
Goal: find whether the container mounts a fixed host path (the main checkout) or the current working dir. Record the answer.

- [ ] **Step 2: Provide dependencies for the worktree**

If `vendor/` / `node_modules/` are missing, symlink from the main checkout to avoid a heavy reinstall:
```bash
ln -s /Users/hunt2412/hieupvdev/project/swinx/vendor vendor
ln -s /Users/hunt2412/hieupvdev/project/swinx/node_modules node_modules
```

- [ ] **Step 3: Confirm the test runner sees worktree files**

Run: `./scripts/dev.sh test --filter=BillingExceptionsQueryTest`
Expected: the test executes (pass/fail both acceptable — we only need execution).
**If the container cannot see worktree files** (fixed mount to main checkout): STOP and report. Fallback options to raise with the human: (a) reconfigure the dev compose to mount the worktree, or (b) run the Dockerized suite from the main checkout against this branch between slices. Do not silently proceed with un-run tests.

- [ ] **Step 4: Capture the finance baseline**

Run: `./scripts/dev.sh test tests/Feature/Finance tests/Unit/Finance` and save the pass/fail tail to the slice notes. Treat these specific failures as pre-existing per memory `finance-test-suite-preexisting-failures`.

---

## Task 1: Reconcile S-009 story docs with the locked decisions

**Why:** Five gaps from review must be reflected before code so the spec and plan agree.

**Files:**
- Modify: `docs/stories/E-finance-module-review-2026-06/S-009-finance-audit-workspace/design.md`
- Modify: `docs/stories/E-finance-module-review-2026-06/S-009-finance-audit-workspace/execplan.md`
- Modify: `docs/stories/E-finance-module-review-2026-06/S-009-finance-audit-workspace/validation.md`

- [ ] **Step 1: Name the shared warning engine in `design.md`**

Under "Application Flow", replace item 4 (`BuildFinanceAuditWarnings`) description so it reads: warnings are produced by a shared, subject-scopeable `App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor` backed by `FinanceInvariantRegistry` (the 15 invariants extracted from `AuditFinanceInvariants`); the command and the workspace consume the same registry. Add a sentence: "Cache drift is computed via `SettlementService` (canonical balance), while invariant `INV-2` remains the DB-level guard in the command — the two are intentionally separate truth layers."

- [ ] **Step 2: Name the two permissions and builder placement in `design.md`**

In "Interface Contract", set the workspace permission to `view_finance_audit_workspace` and name the export permission `export_finance_audit_workspace` (defined now; export endpoint deferred until an audit-log surface exists). In "Application Flow", state that `BuildFinanceLedgerTimeline` and `BuildFinanceAuditWarnings` live under `App\Modules\Finance\Support\Audit\` (pure transforms, no `Query` suffix), while `ResolveFinanceAuditSearchQuery` and `GetFinanceAuditGraphQuery` live under `App\Modules\Finance\Queries\Audit\`.

- [ ] **Step 3: Fix resolver tie-break in `design.md`**

In "Resolver precedence", add: "Free-form `payment.external_ref` only matches when the input does not also match the `student_id` (MSSV) format; an exact `student_id` format always outranks an `external_ref` match."

- [ ] **Step 4: Mirror the decisions in `execplan.md` and `validation.md`**

In `execplan.md` Phase 4, name `FinanceInvariantRegistry`/`FinanceIntegrityAuditor` extraction (command parity preserved) as the first backend work. In `validation.md` add a row: "Unit | Command parity: `finance:audit-invariants` produces identical counts after the registry refactor."

- [ ] **Step 5: Commit**

```bash
git add docs/stories/E-finance-module-review-2026-06/S-009-finance-audit-workspace
git commit -m "docs: reconcile S-009 with locked decisions (shared invariant engine, perms, builder placement)"
```

---

## Task 2: `FinanceAuditScope` value object

**Files:**
- Create: `app/Modules/Finance/Support/Integrity/FinanceAuditScope.php`
- Test: `tests/Unit/Finance/Integrity/FinanceAuditScopeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Integrity\FinanceAuditScope;

it('reports empty when no student ids', function () {
    expect((new FinanceAuditScope())->isEmpty())->toBeTrue();
});

it('exposes a comma-separated int list of student ids', function () {
    $scope = new FinanceAuditScope(studentIds: [3, 1, 2, 2]);
    expect($scope->isEmpty())->toBeFalse()
        ->and($scope->studentIdsCsv())->toBe('1,2,3');
});

it('sanitizes non-positive and non-int ids out of the csv', function () {
    $scope = new FinanceAuditScope(studentIds: [5, 0, -1]);
    expect($scope->studentIdsCsv())->toBe('5');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./scripts/dev.sh test --filter=FinanceAuditScopeTest`
Expected: FAIL ("Class ... not found").

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

final class FinanceAuditScope
{
    /**
     * @param  list<int>  $studentIds
     * @param  list<int>  $invoiceIds
     * @param  list<int>  $paymentIds
     * @param  list<int>  $chargeIds
     * @param  list<int>  $dngRequestIds
     * @param  list<int>  $invoiceLineIds
     */
    public function __construct(
        public readonly array $studentIds = [],
        public readonly array $invoiceIds = [],
        public readonly array $paymentIds = [],
        public readonly array $chargeIds = [],
        public readonly array $dngRequestIds = [],
        public readonly array $invoiceLineIds = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->studentIdsCsv() === '';
    }

    public function studentIdsCsv(): string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $this->studentIds),
            static fn (int $id): bool => $id > 0,
        )));
        sort($ids);

        return implode(',', $ids);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./scripts/dev.sh test --filter=FinanceAuditScopeTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Support/Integrity/FinanceAuditScope.php tests/Unit/Finance/Integrity/FinanceAuditScopeTest.php
git commit -m "feat(finance): add FinanceAuditScope value object for subject-scoped audits"
```

---

## Task 3: `FinanceInvariant` + `FinanceInvariantRegistry` (port the 15 invariants)

**Why:** Single source of truth for "what is a finance integrity violation", usable globally (command) and student-scoped (workspace).

**Files:**
- Create: `app/Modules/Finance/Support/Integrity/FinanceInvariant.php`
- Create: `app/Modules/Finance/Support/Integrity/FinanceInvariantRegistry.php`
- Test: `tests/Feature/Finance/Integrity/FinanceInvariantRegistryTest.php`
- Reference (read, do not modify yet): `app/Console/Commands/AuditFinanceInvariants.php` (source of the 15 SQL strings INV-1..INV-15).

**Design:** each invariant keeps its **exact existing SQL** but with a `{scope}` token spliced into the relevant `WHERE` (added as `AND ({scope})`, or a new `WHERE {scope}` where none exists). The registry stores, per invariant, a `studentScopePredicate` containing an `{ids}` token. The auditor (Task 4) replaces `{scope}` with `1=1` globally, or with the predicate (ids interpolated as a sanitized int CSV) when scoped. This guarantees command parity (global → `AND (1=1)`).

- [ ] **Step 1: Write the failing test (full coverage of all 15)**

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;

it('exposes all 15 invariants with stable codes and a {scope} token', function () {
    $invariants = app(FinanceInvariantRegistry::class)->all();

    $codes = array_map(fn ($i) => $i->code, $invariants);
    expect($codes)->toBe([
        'INV-1', 'INV-2', 'INV-3', 'INV-4', 'INV-5', 'INV-6', 'INV-7', 'INV-8',
        'INV-9', 'INV-10', 'INV-11', 'INV-12', 'INV-13', 'INV-14', 'INV-15',
    ]);

    foreach ($invariants as $invariant) {
        expect($invariant->countSqlTemplate)->toContain('{scope}')
            ->and($invariant->sampleSqlTemplate)->toContain('{scope}')
            ->and($invariant->studentScopePredicate)->toContain('{ids}')
            ->and(in_array($invariant->severity, ['CRITICAL', 'HIGH'], true))->toBeTrue();
    }
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test --filter=FinanceInvariantRegistryTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement `FinanceInvariant`**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

final class FinanceInvariant
{
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $label,
        public readonly string $countSqlTemplate,
        public readonly string $sampleSqlTemplate,
        public readonly string $studentScopePredicate,
    ) {}
}
```

- [ ] **Step 4: Implement `FinanceInvariantRegistry` by porting all 15 SQL strings**

Copy each `count_sql` / `sample_sql` verbatim from `AuditFinanceInvariants::defineInvariants()` into `countSqlTemplate` / `sampleSqlTemplate`, then splice `{scope}` and set `studentScopePredicate` per this table. The splice rule: add `AND ({scope})` to the innermost query's existing `WHERE`; if that query has no `WHERE`, insert `WHERE {scope}` before its `GROUP BY`. The wrapping `SELECT COUNT(*) c FROM (...) t` is unchanged.

| Code | Innermost table alias | `{scope}` goes into | `studentScopePredicate` |
| --- | --- | --- | --- |
| INV-1 | `payments p` | add `WHERE {scope}` before `GROUP BY p.id` | `p.student_id IN ({ids})` |
| INV-2 | `student_invoices si` | append `AND ({scope})`? no existing WHERE → add `WHERE {scope}` before `GROUP BY si.id` | `si.student_id IN ({ids})` |
| INV-3 | `finance_charges fc` | existing `WHERE fc.status="active" AND fc.amount>0` → append `AND ({scope})` | `fc.student_id IN ({ids})` |
| INV-4 | `invoice_lines il` | existing `WHERE il.status="void"` → append `AND ({scope})` | `il.invoice_id IN (SELECT id FROM student_invoices WHERE student_id IN ({ids}))` |
| INV-5 | `invoice_discounts idc` | existing `WHERE idc.status="reversed"` → append `AND ({scope})` | `idc.invoice_id IN (SELECT id FROM student_invoices WHERE student_id IN ({ids}))` |
| INV-6 | `student_invoices` | add `WHERE {scope}` before `GROUP BY student_id, semester_id` | `student_id IN ({ids})` |
| INV-7 | `student_scholarship_awards` | add `WHERE {scope}` before `GROUP BY student_id` | `student_id IN ({ids})` |
| INV-8 | `finance_charges fc` | existing `WHERE fc.status="active" AND fc.amount>0` → append `AND ({scope})` | `fc.student_id IN ({ids})` |
| INV-9 | `student_invoices` | add `WHERE {scope}` before `GROUP BY invoice_number` | `student_id IN ({ids})` |
| INV-10 | `payments` | existing `WHERE amount<=0` → append `AND ({scope})` | `student_id IN ({ids})` |
| INV-11 | `dng_webhook_events` | add `WHERE {scope}` before `GROUP BY payload_hash` | `dng_payment_request_id IN (SELECT id FROM dng_payment_requests WHERE student_id IN ({ids}))` |
| INV-12 | `dng_payment_requests dpr` | existing `WHERE dpr.payment_id IS NOT NULL AND ...` → append `AND ({scope})` | `dpr.student_id IN ({ids})` |
| INV-13 | `finance_charges fc` | existing `WHERE fc.status="void" AND ...` → append `AND ({scope})` | `fc.student_id IN ({ids})` |
| INV-14 | `dng_payment_requests dpr` | add `WHERE {scope}` before `GROUP BY dpr.id, dpr.amount` | `dpr.student_id IN ({ids})` |
| INV-15 | `dng_payment_request_charges dprc` | existing `WHERE dprc.finance_charge_installment_id IS NOT NULL AND ...` → append `AND ({scope})` | `dprc.dng_payment_request_id IN (SELECT id FROM dng_payment_requests WHERE student_id IN ({ids}))` |

Shape of the class:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

class FinanceInvariantRegistry
{
    /** @return list<FinanceInvariant> */
    public function all(): array
    {
        return [
            new FinanceInvariant(
                'INV-1',
                'CRITICAL',
                'Payment over-allocated (SUM applications > payment.amount)',
                'SELECT COUNT(*) c FROM (
                    SELECT p.id FROM payments p
                    JOIN payment_applications pa ON pa.payment_id = p.id
                    WHERE {scope}
                    GROUP BY p.id, p.amount HAVING SUM(pa.amount) > p.amount + 0.01
                ) t',
                'SELECT p.id FROM payments p
                    JOIN payment_applications pa ON pa.payment_id = p.id
                    WHERE {scope}
                    GROUP BY p.id, p.amount HAVING SUM(pa.amount) > p.amount + 0.01 LIMIT 5',
                'p.student_id IN ({ids})',
            ),
            // ... INV-2 .. INV-15 ported per the table above ...
        ];
    }

    public function find(string $code): ?FinanceInvariant
    {
        foreach ($this->all() as $invariant) {
            if ($invariant->code === $code) {
                return $invariant;
            }
        }

        return null;
    }
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `./scripts/dev.sh test --filter=FinanceInvariantRegistryTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Finance/Support/Integrity/FinanceInvariant.php app/Modules/Finance/Support/Integrity/FinanceInvariantRegistry.php tests/Feature/Finance/Integrity/FinanceInvariantRegistryTest.php
git commit -m "feat(finance): extract 15 finance invariants into a scopeable registry"
```

---

## Task 4: `FinanceIntegrityAuditor`

**Files:**
- Create: `app/Modules/Finance/Support/Integrity/FinanceIntegrityAuditor.php`
- Test: `tests/Feature/Finance/Integrity/FinanceIntegrityAuditorTest.php`

- [ ] **Step 1: Write the failing test (global + scoped detection)**

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedOverAllocatedPayment(Student $student): Payment
{
    // Payment whose applications sum exceeds its amount -> violates INV-1.
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 100,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'manual',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    // applications reference invoice_line_id; for INV-1 only the sum matters, so a
    // raw insert with a dummy non-null line id under a fresh invoice line is used.
    // Use a real line via the helper in the Feature suite if FK constraints require it.
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => makeInvoiceLineForStudent($student)->id,
        'amount' => 250,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    return $payment;
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
});

it('summarize() global counts INV-1 over-allocation', function () {
    $student = Student::factory()->forCampus($this->campus)->forProgram($this->program)->create();
    seedOverAllocatedPayment($student);

    $summary = collect(app(FinanceIntegrityAuditor::class)->summarize(null))
        ->keyBy('code');

    expect($summary['INV-1']['count'])->toBe(1);
});

it('findForScope() returns INV-1 only for the offending student', function () {
    $bad = Student::factory()->forCampus($this->campus)->forProgram($this->program)->create();
    $good = Student::factory()->forCampus($this->campus)->forProgram($this->program)->create();
    seedOverAllocatedPayment($bad);

    $findingsBad = app(FinanceIntegrityAuditor::class)
        ->findForScope(new FinanceAuditScope(studentIds: [$bad->id]));
    $findingsGood = app(FinanceIntegrityAuditor::class)
        ->findForScope(new FinanceAuditScope(studentIds: [$good->id]));

    expect(collect($findingsBad)->pluck('code'))->toContain('INV-1')
        ->and(collect($findingsGood)->pluck('code'))->not->toContain('INV-1');
});

it('findForScope() returns nothing for an empty scope rather than scanning globally', function () {
    $student = Student::factory()->forCampus($this->campus)->forProgram($this->program)->create();
    seedOverAllocatedPayment($student);

    expect(app(FinanceIntegrityAuditor::class)->findForScope(new FinanceAuditScope()))->toBe([]);
});
```

Note: implement `makeInvoiceLineForStudent()` as a small Pest helper in the test file that creates a `StudentInvoice` + `InvoiceLine` (active) for the student so the `payment_applications.invoice_line_id` FK is satisfied. Model the invoice/line creation on `tests/Feature/Finance/LedgerSourceOfTruthTest.php`.

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test --filter=FinanceIntegrityAuditorTest`
Expected: FAIL (class not found).

- [ ] **Step 3: Implement the auditor**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

use Illuminate\Support\Facades\DB;
use Throwable;

class FinanceIntegrityAuditor
{
    public function __construct(private readonly FinanceInvariantRegistry $registry) {}

    /**
     * Global run for the command. @return list<array{code:string,severity:string,label:string,count:int}>
     */
    public function summarize(?FinanceAuditScope $scope = null): array
    {
        return array_map(function (FinanceInvariant $invariant) use ($scope): array {
            return [
                'code' => $invariant->code,
                'severity' => $invariant->severity,
                'label' => $invariant->label,
                'count' => $this->count($invariant, $scope),
            ];
        }, $this->registry->all());
    }

    public function count(FinanceInvariant $invariant, ?FinanceAuditScope $scope = null): int
    {
        $sql = $this->resolve($invariant->countSqlTemplate, $invariant, $scope);

        try {
            return (int) (DB::selectOne($sql)->c ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return list<int> */
    public function samples(FinanceInvariant $invariant, ?FinanceAuditScope $scope = null): array
    {
        $sql = $this->resolve($invariant->sampleSqlTemplate, $invariant, $scope);

        try {
            return array_values(array_map(static fn ($row): int => (int) $row->id, DB::select($sql)));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Subject-scoped findings: only invariants with count > 0 for this student scope.
     * Empty scope returns [] (never a global scan).
     *
     * @return list<array{code:string,severity:string,label:string,sample_ids:list<int>}>
     */
    public function findForScope(FinanceAuditScope $scope): array
    {
        if ($scope->isEmpty()) {
            return [];
        }

        $findings = [];
        foreach ($this->registry->all() as $invariant) {
            if ($this->count($invariant, $scope) > 0) {
                $findings[] = [
                    'code' => $invariant->code,
                    'severity' => $invariant->severity,
                    'label' => $invariant->label,
                    'sample_ids' => $this->samples($invariant, $scope),
                ];
            }
        }

        return $findings;
    }

    private function resolve(string $template, FinanceInvariant $invariant, ?FinanceAuditScope $scope): string
    {
        if ($scope === null || $scope->isEmpty()) {
            return str_replace('{scope}', '1=1', $template);
        }

        // studentIdsCsv() is sanitized to positive ints only — safe to interpolate.
        $predicate = str_replace('{ids}', $scope->studentIdsCsv(), $invariant->studentScopePredicate);

        return str_replace('{scope}', $predicate, $template);
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `./scripts/dev.sh test --filter=FinanceIntegrityAuditorTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Support/Integrity/FinanceIntegrityAuditor.php tests/Feature/Finance/Integrity/FinanceIntegrityAuditorTest.php
git commit -m "feat(finance): add FinanceIntegrityAuditor with global and student-scoped runs"
```

---

## Task 5: Refactor `AuditFinanceInvariants` command onto the auditor (parity)

**Files:**
- Modify: `app/Console/Commands/AuditFinanceInvariants.php`
- Test: `tests/Feature/Finance/Integrity/AuditFinanceInvariantsParityTest.php`

- [ ] **Step 1: Write the failing parity test**

Seed one known violation (reuse the over-allocated payment helper from Task 4 — extract it to `tests/Feature/Finance/Integrity/Helpers.php` or duplicate). Then assert the command still reports it.

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('command still detects an over-allocated payment after the registry refactor', function () {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create();
    seedOverAllocatedPayment($student); // shared helper

    $this->artisan('finance:audit-invariants')
        ->expectsOutputToContain('INV-1')
        ->expectsOutputToContain('1 total offending')
        ->assertSuccessful();
});
```

- [ ] **Step 2: Run to verify it fails or passes pre-refactor**

Run: `./scripts/dev.sh test --filter=AuditFinanceInvariantsParityTest`
Expected: PASS against the current command (proves the assertion is correct), giving a safety net before refactor.

- [ ] **Step 3: Refactor the command to consume the registry + auditor**

Replace the private `$invariants` array and `defineInvariants()` with a constructor-injected `FinanceInvariantRegistry`/`FinanceIntegrityAuditor`. Build the output table from `$this->auditor->summarize(null)`; for `--sample`, call `$this->auditor->samples($invariant, null)` (fetch each `FinanceInvariant` via `$registry->all()`). Keep the exact header lines, the context row-count table, the `✅ 0` / `❌ {n}` rendering, the `{totalBad} total offending rows/groups` summary, and `return self::SUCCESS;`.

```php
public function __construct(
    private readonly FinanceInvariantRegistry $registry,
    private readonly FinanceIntegrityAuditor $auditor,
) {
    parent::__construct();
}
```

- [ ] **Step 4: Run the parity test + the registry/auditor tests together**

Run: `./scripts/dev.sh test --filter="AuditFinanceInvariantsParityTest|FinanceInvariantRegistryTest|FinanceIntegrityAuditorTest"`
Expected: PASS. Also run `./scripts/dev.sh artisan finance:audit-invariants --sample` and confirm the table renders with no errors.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/AuditFinanceInvariants.php tests/Feature/Finance/Integrity/AuditFinanceInvariantsParityTest.php
git commit -m "refactor(finance): drive finance:audit-invariants from shared registry (parity preserved)"
```

**SLICE 1 GATE (review checkpoint):** request review of Tasks 0–5 (the shared integrity engine) before continuing.

---

## Task 6: Define and seed the two workspace permissions

**Files:**
- Modify: `config/permission.php`
- Test: `tests/Feature/Finance/Audit/FinanceAuditPermissionsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

it('registers the audit workspace permission codes in config', function () {
    $codes = collect(config('permission.access'))->flatMap(fn ($actions) => array_values($actions));

    expect($codes)->toContain('view_finance_audit_workspace')
        ->and($codes)->toContain('export_finance_audit_workspace');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test --filter=FinanceAuditPermissionsTest`
Expected: FAIL.

- [ ] **Step 3: Add the codes**

Locate the finance module block in `config/permission.php` (where `view_finance_invoices` / `view_finance_payments` are defined). Add a new module group near it:

```php
'finance_audit' => [
    'view_audit_workspace' => 'view_finance_audit_workspace',
    'export_audit_workspace' => 'export_finance_audit_workspace',
],
```

- [ ] **Step 4: Run to verify it passes + seed**

Run: `./scripts/dev.sh test --filter=FinanceAuditPermissionsTest` → PASS.
Run: `./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder` → confirm "Created new permission: view_finance_audit_workspace" and the export one.

- [ ] **Step 5: Commit**

```bash
git add config/permission.php tests/Feature/Finance/Audit/FinanceAuditPermissionsTest.php
git commit -m "feat(finance): add view/export finance audit workspace permissions"
```

---

## Task 7: `ResolveFinanceAuditSearchQuery`

**Files:**
- Create: `app/Modules/Finance/Queries/Audit/ResolveFinanceAuditSearchQuery.php`
- Test: `tests/Feature/Finance/Audit/ResolveFinanceAuditSearchQueryTest.php`
- Reference: `app/Models/StudentInvoice.php` (`scopeSearch`), `app/Models/Payment.php`, `app/Modules/Finance/Dng/Models/DngPaymentRequest.php`, `app/Models/Student.php`.

**Contract:** `handle(?string $q, ?int $campusId): array` returns the **Resolution** shape. Precedence (design.md): (1) exact `invoice_number`; (2) DNG `item_id` → `dng_payment_id` → `dng_transaction_id`; (3) `payment.external_ref` **only if `$q` is not MSSV-shaped**; (4) exact `students.student_id` (MSSV); (5) student name/email fuzzy (`Student` search) → may be ambiguous; (6) explicit prefixes `payment:<id>` / `invoice:<id>` / `dng:<id>` / `charge:<id>`. Bare integers never guess. **Every candidate is filtered to `$campusId`** (resolve the owning student's campus) before inclusion; cross-campus rows are invisible (treated as no-match, never "denied").

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Queries\Audit\ResolveFinanceAuditSearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
});

it('resolves an exact invoice number to a single invoice target', function () {
    $student = Student::factory()->forCampus($this->campus)->forProgram($this->program)->create();
    $invoice = StudentInvoice::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'invoice_number' => 'INV-RES-001',
    ]);

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('INV-RES-001', $this->campus->id);

    expect($result['status'])->toBe('single')
        ->and($result['target'])->toBe(['type' => 'invoice', 'id' => $invoice->id]);
});

it('resolves an exact student code to a student target, outranking a colliding external_ref', function () {
    $student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['student_id' => 'SWB123'])->create();

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('SWB123', $this->campus->id);

    expect($result['status'])->toBe('single')
        ->and($result['target'])->toBe(['type' => 'student', 'id' => $student->id]);
});

it('never resolves a bare integer to a guessed entity', function () {
    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('12345', $this->campus->id);
    expect($result['status'])->toBe('empty');
});

it('does not surface a same-code record from another campus', function () {
    $student = Student::factory()->forCampus($this->otherCampus)->forProgram($this->program)
        ->state(['student_id' => 'SWB999'])->create();

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('SWB999', $this->campus->id);
    expect($result['status'])->toBe('empty');
});

it('returns ambiguous when a name matches multiple students', function () {
    Student::factory()->count(2)->forCampus($this->campus)->forProgram($this->program)
        ->state(['full_name' => 'Nguyen Van A'])->create();

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle('Nguyen Van A', $this->campus->id);
    expect($result['status'])->toBe('ambiguous')
        ->and(count($result['matches']))->toBeGreaterThanOrEqual(2);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test --filter=ResolveFinanceAuditSearchQueryTest`
Expected: FAIL.

- [ ] **Step 3: Implement the resolver**

Implement `handle()` walking the precedence list, each lookup scoped to `$campusId` via the owning student's campus relation. Define a private `looksLikeStudentCode(string $q): bool` (MSSV format — letters+digits, e.g. `^[A-Za-z]{2,}\d{2,}$`; confirm the real format against existing `students.student_id` data before finalizing the regex). Return the Resolution shape. Build a private `studentMatch`/`invoiceMatch`/etc. helper to format `matches` rows (`label`, `sublabel`). Prefix handling: if `$q` matches `^(payment|invoice|dng|charge):(\d+)$`, resolve that id directly (still campus-checked).

- [ ] **Step 4: Run to verify it passes**

Run: `./scripts/dev.sh test --filter=ResolveFinanceAuditSearchQueryTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Queries/Audit/ResolveFinanceAuditSearchQuery.php tests/Feature/Finance/Audit/ResolveFinanceAuditSearchQueryTest.php
git commit -m "feat(finance): add deterministic campus-scoped audit search resolver"
```

---

## Task 8: `GetFinanceAuditGraphQuery`

**Files:**
- Create: `app/Modules/Finance/Queries/Audit/GetFinanceAuditGraphQuery.php`
- Test: `tests/Feature/Finance/Audit/GetFinanceAuditGraphQueryTest.php`
- Reuse: `GetPaymentDetailsQuery`, `SettlementService`, `app/Models/*` relationships.

**Contract:** `handle(array $target, ?int $semesterId = null, ?int $billingCycleId = null): array` returns `['subject' => [...], 'student_id' => int, 'nodes' => list<node>, 'edges' => list<edge>, 'derived_balance' => list<invoice cache-vs-derived rows>]`. Resolves the target to its owning **student**, then loads that student's charges/invoices/lines/payments/applications/discounts/allocations/DNG requests + pivots, **bounded** by `$semesterId`/`$billingCycleId` when provided (default: most recent semester with finance activity — never an unbounded all-history scan). Derived numbers come from `SettlementService`; `derived_balance[]` carries `{invoice_id, invoice_number, cached_total_amount, cached_paid_amount, derived_net, derived_paid, drift => bool}`.

- [ ] **Step 1: Write the failing test (fan-out: 1 payment → many lines; 1 DNG → many charges)**

```php
<?php

declare(strict_types=1);

// Build: one student, one invoice with 2 active lines (2 charges), one payment
// applied across BOTH lines, one DNG request linked to BOTH charges via
// dng_payment_request_charges. Assert the graph contains both charge nodes, the
// payment node, the dng node, and edges payment->line(x2) and dng->charge(x2).
use App\Modules\Finance\Queries\Audit\GetFinanceAuditGraphQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders payment fan-out and DNG multi-charge edges', function () {
    [$student, $invoice, $lines, $payment, $dng, $charges] = seedFanOutFixture(); // helper

    $graph = app(GetFinanceAuditGraphQuery::class)
        ->handle(['type' => 'student', 'id' => $student->id]);

    $nodeKeys = collect($graph['nodes'])->pluck('key');
    expect($nodeKeys)->toContain("payment:{$payment->id}")
        ->and($nodeKeys)->toContain("dng:{$dng->id}");
    foreach ($charges as $charge) {
        expect($nodeKeys)->toContain("charge:{$charge->id}");
    }

    $paymentEdges = collect($graph['edges'])->where('from', "payment:{$payment->id}");
    expect($paymentEdges->count())->toBe(2);

    $dngEdges = collect($graph['edges'])->where('from', "dng:{$dng->id}")->where('kind', 'dng_charge');
    expect($dngEdges->count())->toBe(2);
});

it('flags cache drift in derived_balance when cached_paid_amount is stale', function () {
    [$student, $invoice] = seedStaleCacheFixture(); // helper sets cached_paid_amount wrong

    $graph = app(GetFinanceAuditGraphQuery::class)
        ->handle(['type' => 'invoice', 'id' => $invoice->id]);

    $row = collect($graph['derived_balance'])->firstWhere('invoice_id', $invoice->id);
    expect($row['drift'])->toBeTrue();
});
```

Implement `seedFanOutFixture()` / `seedStaleCacheFixture()` helpers in the test file modeled on `tests/Feature/Finance/VoidReleaseAllocationTest.php` and `tests/Feature/Finance/InvoiceCacheRebuildTest.php`.

- [ ] **Step 2: Run to verify it fails** — `./scripts/dev.sh test --filter=GetFinanceAuditGraphQueryTest` → FAIL.

- [ ] **Step 3: Implement `handle()`** composing existing relations + `SettlementService::deriveInvoiceSnapshot()` for `derived_*`, with `drift = abs(cached_paid_amount - derived_paid) > 0.01 || abs(cached_total_amount - derived_net) > 0.01`. Bound the query by semester/billing cycle.

- [ ] **Step 4: Run to verify it passes** — `./scripts/dev.sh test --filter=GetFinanceAuditGraphQueryTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Queries/Audit/GetFinanceAuditGraphQuery.php tests/Feature/Finance/Audit/GetFinanceAuditGraphQueryTest.php
git commit -m "feat(finance): build student-scoped audit money graph with cache-drift derivation"
```

---

## Task 9: `BuildFinanceLedgerTimeline`

**Files:**
- Create: `app/Modules/Finance/Support/Audit/BuildFinanceLedgerTimeline.php`
- Test: `tests/Unit/Finance/Audit/BuildFinanceLedgerTimelineTest.php`

**Contract:** `build(array $graph): array` returns a chronological list of **Timeline event**s built from graph nodes/edges. Preserves signed amounts: a `payment_application` with `entry_type=reversal` (negative `amount`) becomes a `signed_amount < 0` event labelled "Payment reversal"; allocations/releases likewise. Pure function (no DB) — input is the graph from Task 8, so the graph must carry application/allocation entry rows. (If Task 8's node set lacks raw ledger entries, extend its output with a `ledger_entries` array — adjust both tasks to agree on that key.)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Audit\BuildFinanceLedgerTimeline;

it('preserves signed reversal entries and orders by time', function () {
    $graph = [
        'ledger_entries' => [
            ['at' => '2026-01-02 10:00:00', 'type' => 'payment_application', 'amount' => -50.0, 'entry_type' => 'reversal', 'refs' => ['payment' => 1, 'invoice_line' => 9]],
            ['at' => '2026-01-01 09:00:00', 'type' => 'payment_application', 'amount' => 200.0, 'entry_type' => 'application', 'refs' => ['payment' => 1, 'invoice_line' => 9]],
        ],
    ];

    $timeline = (new BuildFinanceLedgerTimeline())->build($graph);

    expect($timeline[0]['at'])->toBe('2026-01-01 09:00:00')
        ->and($timeline[0]['signed_amount'])->toBe(200.0)
        ->and($timeline[1]['signed_amount'])->toBe(-50.0)
        ->and($timeline[1]['label'])->toContain('reversal');
});
```

- [ ] **Step 2–5:** Run → FAIL; implement the pure transform; run → PASS; commit `feat(finance): add signed finance ledger timeline builder`.

---

## Task 10: `BuildFinanceAuditWarnings`

**Files:**
- Create: `app/Modules/Finance/Support/Audit/BuildFinanceAuditWarnings.php`
- Test: `tests/Feature/Finance/Audit/BuildFinanceAuditWarningsTest.php`

**Contract:** `build(FinanceAuditScope $scope): array` returns a list of **Warning**s. It (a) calls `FinanceIntegrityAuditor::findForScope($scope)` → maps to `kind => 'invariant'`; and (b) computes `kind => 'cache_drift'` warnings via `SettlementService::deriveInvoiceSnapshot()` for each of the scope's student invoices where cached vs derived differ by > 0.01. Cache-drift uses `SettlementService` (canonical balance); INV-2 remains the command's DB guard — overlap is intentional and documented.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Audit\BuildFinanceAuditWarnings;
use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('emits a cache_drift warning when cached_paid_amount disagrees with SettlementService', function () {
    [$student, $invoice] = seedStaleCacheFixture(); // reuse helper shape from Task 8

    $warnings = app(BuildFinanceAuditWarnings::class)
        ->build(new FinanceAuditScope(studentIds: [$student->id]));

    expect(collect($warnings)->where('kind', 'cache_drift')->pluck('sample_ids')->flatten())
        ->toContain($invoice->id);
});

it('emits invariant warnings only for the scoped student', function () {
    $bad = makeStudentWithOverAllocatedPayment(); // reuse Task 4 helper
    $warnings = app(BuildFinanceAuditWarnings::class)
        ->build(new FinanceAuditScope(studentIds: [$bad->id]));

    expect(collect($warnings)->where('kind', 'invariant')->pluck('code'))->toContain('INV-1');
});
```

- [ ] **Step 2–5:** Run → FAIL; implement; run → PASS; commit `feat(finance): add subject-scoped finance audit warnings (invariants + cache drift)`.

**SLICE 2 GATE (review checkpoint):** request review of Tasks 6–10 (permissions + backend read layer) before building the HTTP surface.

---

## Task 11: `FinanceAuditSearchRequest`

**Files:**
- Create: `app/Modules/Finance/Http/Requests/Audit/FinanceAuditSearchRequest.php`
- Test: `tests/Feature/Finance/Audit/FinanceAuditSearchRequestTest.php`
- Reference: `app/Modules/Finance/Http/Requests/Operations/ListLifecycleDueExceptionsRequest.php`.

- [ ] **Step 1: Write the failing test** — assert `authorize()` is false without `view_finance_audit_workspace` and true with it (mock `PermissionService` or use a Gate fake), and that `rules()` accepts `q,target_type,target_id,semester_id,billing_cycle_id` and rejects an invalid `target_type`.

- [ ] **Step 2–5:** Implement:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Audit;

use Illuminate\Foundation\Http\FormRequest;

class FinanceAuditSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_finance_audit_workspace') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'target_type' => 'nullable|string|in:student,invoice,payment,charge,dng',
            'target_id' => 'nullable|integer|min:1',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'billing_cycle_id' => 'nullable|integer|exists:billing_cycles,id',
        ];
    }
}
```

Run → PASS; commit `feat(finance): add finance audit search FormRequest`.

---

## Task 12: `FinanceAuditWorkspaceController` + route

**Files:**
- Create: `app/Modules/Finance/Http/Web/Admin/FinanceAuditWorkspaceController.php`
- Modify: `app/Modules/Finance/routes/web.php`
- Test: `tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php`
- Reference: `app/Modules/Finance/Http/Web/Admin/PaymentController.php` (Inertia render), Inertia v3 `Inertia::defer`.

**Contract:** `index(FinanceAuditSearchRequest $request)`:
1. Resolve `app('campus')?->id`.
2. If `target_type`+`target_id` present, use them; else call `ResolveFinanceAuditSearchQuery`.
3. Always return `filters`, `resolution`, `links`, `allowed_actions`.
4. When a single target is resolved, return `graph`, `timeline`, `warnings` as **deferred props** (`Inertia::defer(fn () => ...)`), each rebuilding the student scope and **re-checking campus** inside the closure.
5. `allowed_actions` includes `export` only if `$request->user()->can('export_finance_audit_workspace')` — but the export endpoint itself is **deferred from MVP** (button rendered disabled with a "coming soon" note; no data egress route shipped yet).

- [ ] **Step 1: Write the failing tests** (model on `DngAdminPagesTest`, use `AssertableInertia`):

```php
it('renders the workspace and resolves a visible invoice', function () {
    // grant view_finance_audit_workspace via PermissionService mock; bind campus
    // ... seed an invoice for the campus ...
    actingAs($user)->get('/finance/audit?q=INV-RES-001')
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Audit/Workspace')
            ->where('resolution.status', 'single')
            ->where('resolution.target.type', 'invoice'));
});

it('denies access without the permission', function () {
    // PermissionService returns [] -> 403
    actingAs($user)->get('/finance/audit')->assertForbidden();
});

it('does not reveal a cross-campus invoice number', function () {
    // invoice belongs to otherCampus; current campus session is campus
    actingAs($user)->get('/finance/audit?q=INV-OTHER-001')
        ->assertInertia(fn ($page) => $page->where('resolution.status', 'empty'));
});
```

- [ ] **Step 2: Run to verify it fails** — route/controller missing → FAIL.

- [ ] **Step 3: Add the route** in `app/Modules/Finance/routes/web.php` inside the `finance.` group:

```php
use App\Modules\Finance\Http\Web\Admin\FinanceAuditWorkspaceController;

// Audit Workspace (read-only)
Route::get('/audit', [FinanceAuditWorkspaceController::class, 'index'])
    ->middleware('can:view_finance_audit_workspace')
    ->name('audit.index');
```

- [ ] **Step 4: Implement the controller** per the contract; run tests → PASS.

- [ ] **Step 5: Commit** `feat(finance): add finance audit workspace controller and route`.

---

## Task 13: Inertia page `Finance/Audit/Workspace.vue`

**REQUIRED SUB-SKILL:** the implementer MUST invoke the **swinx-frontend** skill before writing this file and follow it (useForm vs router, deferred prop consumption via `<Deferred>`, `@/components/ui` primitives, snake_case props, route helpers, no raw URL/`fetch`/`window.confirm`).

**Files:**
- Create: `resources/js/pages/Finance/Audit/Workspace.vue`
- (Optional, if the page exceeds ~300 lines) split panels into `resources/js/pages/Finance/Audit/components/*.vue` (SearchBar, ResolutionPicker, GraphSummary, LedgerTimeline, DerivedBalancePanel, WarningPanel).
- Test: extend `tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php` with an `assertInertia` prop-shape assertion (no separate JS unit test required for MVP; visual proof comes from browser smoke in Task 15).

**Props contract (snake_case, matches the controller):** `filters`, `resolution`, `graph` (deferred), `timeline` (deferred), `warnings` (deferred), `links`, `allowed_actions`.

- [ ] **Step 1: Build the page** with: a universal search bar (`useForm` GET to `finance.audit.index` via route helper); a resolution area handling `empty` / `single` / `ambiguous` (ambiguous → clickable `matches`); subject header; `<Deferred>` blocks for graph summary, signed ledger timeline (render `signed_amount` with sign + color), derived-balance panel (cached vs derived, highlight `drift`), and warning panel (group by `severity`); lightweight actions (copy authenticated link, refresh, open source page via `links`, export button **disabled** with tooltip). Follow web design-quality rules — intentional hierarchy, not a default card grid.

- [ ] **Step 2: Verify** — `./scripts/dev.sh npm run type-check` and `./scripts/dev.sh npm run lint` clean; the page test's `assertInertia` passes.

- [ ] **Step 3: Commit** `feat(finance): add finance audit workspace Inertia page`.

---

## Task 14: Promote Audit Workspace in the Finance Office menu

**Files:**
- Modify: `resources/js/constants/menu-sidebar.ts`

- [ ] **Step 1: Add the entry** as the **first** item under the `Finance Office` group (label at ~line 315), above existing sections:

```ts
{
    title: 'Audit Workspace',
    href: '/finance/audit',
    icon: Search, // import from 'lucide-vue-next' if not already imported
    requiredPermissions: ['view_finance_audit_workspace'],
},
```

Keep Charges/Invoices/Payments/DNG entries as-is (they remain specialist deep-dive pages).

- [ ] **Step 2: Verify** — `./scripts/dev.sh npm run type-check` clean; confirm `Search` icon import exists.

- [ ] **Step 3: Commit** `feat(finance): surface Audit Workspace as primary Finance Office entry`.

---

## Task 15: Full verification + finishing

**Files:** none (verification + branch completion).

- [ ] **Step 1: Backend suite** — `./scripts/dev.sh test tests/Feature/Finance tests/Unit/Finance`. Compare failures against the Task 0 baseline; **only new green, no new red**.

- [ ] **Step 2: Targeted audit suite** — `./scripts/dev.sh test --filter="Audit|Integrity|FinanceInvariant"` all PASS.

- [ ] **Step 3: Static + format** — `./scripts/dev.sh artisan pint`, `./scripts/dev.sh npm run type-check`, `./scripts/dev.sh npm run lint`, `./scripts/dev.sh npm run format:check`.

- [ ] **Step 4: Invariants evidence** — `./scripts/dev.sh artisan finance:audit-invariants --sample` runs cleanly (command parity intact).

- [ ] **Step 5: Browser smoke** (primary Finance UI surface, required): search → resolve single → see timeline/derived-balance/warnings; ambiguous → pick; shared link reopened in a fresh session reruns access control; cross-campus identifier returns empty, not denied. Capture screenshots.

- [ ] **Step 6: Finishing** — **REQUIRED SUB-SKILL:** use `superpowers:finishing-a-development-branch` to verify tests, present merge/PR options, and complete. Record acceptance evidence (command output + browser notes + baseline delta) in the story's Harness.

---

## Self-review notes (author)

- **Spec coverage:** universal search (T7), graph not chain (T8), settlement-derived + cache drift (T8/T10), signed ledger timeline (T9), shared invariant warnings (T3–T5,T10), permissions + campus scoping + non-leaky not-found (T6,T7,T12), deferred props (T12,T13), menu IA (T14), export deferred-but-permissioned (T6,T12,T13). All present.
- **Deferred / explicitly out of MVP:** export data egress endpoint (permission defined, button disabled) — matches the story stop condition "export deferred if no audit-log surface"; raise as a follow-up slice when an audit-log surface is chosen.
- **Cross-task type consistency:** `FinanceAuditScope` fields, the Resolution/node/edge/timeline/warning shapes, and `ledger_entries` (shared between T8 and T9) are defined once in "Conventions" and reused verbatim. If T8 cannot cheaply emit `ledger_entries`, update T8 and T9 together.
- **Risk:** the `{scope}` SQL splice (T3) is the only place raw ids touch SQL; `studentIdsCsv()` sanitizes to positive ints so interpolation is injection-safe. Confirm the MSSV regex (T7) against real `students.student_id` values before finalizing.
