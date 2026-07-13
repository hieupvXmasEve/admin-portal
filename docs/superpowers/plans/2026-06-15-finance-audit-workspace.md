# Finance Audit Workspace (S-009 / FIN-REV-009) Implementation Plan

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

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
  - **Warning:** `['code' => string, 'severity' => string, 'label' => string, 'kind' => 'invariant'|'invariant_error'|'cache_drift', 'sample_ids' => list<int>]`. `kind=invariant_error` means the invariant SQL itself failed (severity `ERROR`) — the workspace renders "audit unavailable" for that check, never a clean ✅.

---

## Task 0: Establish a runnable test baseline in the worktree

**Why:** Worktrees do not share `vendor/`/`node_modules/`, tests run in Docker, and `tests/Feature/Finance` has a body of **pre-existing failures** (memory `finance-test-suite-preexisting-failures` recorded ~26 fail / 324 pass on 2026-06-14 — treat that number as a hint, not truth). Re-capture the **actual** baseline in this worktree so new failures are distinguishable from inherited ones.

**Files:** none (environment only).

- [ ] **Step 1: Determine how Docker mounts the project**

Run: `sed -n '1,80p' scripts/dev.sh` and `cat docker/docker-compose*.yml 2>/dev/null || cat docker-compose*.yml`
Goal: find whether the container mounts a fixed host path (the main checkout) or the current working dir. Record the answer.

- [ ] **Step 2: Provide dependencies for the worktree (detect first)**

Only act if a dependency dir is missing. Detect the main checkout dynamically instead of hardcoding a path:
```bash
MAIN=$(git -C "$(git rev-parse --git-common-dir)/.." rev-parse --show-toplevel 2>/dev/null \
  || git worktree list --porcelain | awk 'NR==1{print $2}')
[ -e vendor ]       || ln -s "$MAIN/vendor" vendor
[ -e node_modules ] || ln -s "$MAIN/node_modules" node_modules
```
If `$MAIN` cannot be resolved or its `vendor/`/`node_modules/` are themselves absent, STOP and report rather than symlinking a bad target.

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

Under "Application Flow", move the registry/auditor out of `Support/Audit` into `App\Modules\Finance\Support\Integrity\` and rewrite item 4 so it reads: warnings are produced by a shared `App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor` backed by `App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry` (the 15 invariants extracted from `AuditFinanceInvariants`); the command and the workspace consume the same registry. Add: "The auditor never swallows a failing invariant — a SQL error surfaces as an `ERROR`/`invariant_error` result so the command still prints `ERROR` and the workspace shows 'audit unavailable', never a false ✅." Add: "Cache drift is computed via `SettlementService` (canonical balance), while invariant `INV-2` remains the DB-level guard in the command — the two are intentionally separate truth layers." Note the scope is **student-resolved**: any searched subject (invoice/payment/DNG/charge) is reduced to its owning student's id set before invariants run.

- [ ] **Step 2: Name the two permissions and builder placement in `design.md`**

In "Interface Contract", set the workspace permission to `view_finance_audit_workspace` and name the export permission `export_finance_audit_workspace` (**defined and seeded now; export endpoint + audit-log deferred** until an audit-log surface exists — the button ships disabled). Lock the namespace convention in "Application Flow":
- **`App\Modules\Finance\Support\Integrity\`** — the shared engine reused by command + workspace: `FinanceAuditScope`, `FinanceInvariant`, `FinanceInvariantRegistry`, `FinanceIntegrityAuditor`.
- **`App\Modules\Finance\Support\Audit\`** — workspace-only pure transforms (repo noun+role naming, no `Build*` verb prefix): `FinanceLedgerTimelineBuilder`, `FinanceAuditWarningBuilder`.
- **`App\Modules\Finance\Queries\Audit\`** — `ResolveFinanceAuditSearchQuery`, `GetFinanceAuditGraphQuery`.

- [ ] **Step 3: Confirm resolver precedence (design.md is the source of truth)**

`design.md:79-90` already locks the precedence: **(1) explicit prefixes `payment:`/`invoice:`/`dng:`/`charge:`**, (2) exact `invoice_number`, (3) DNG `item_id`→`dng_payment_id`→`dng_transaction_id`, (4) exact `students.student_id`, (5) `payment.external_ref` (only after invoice/DNG/exact-student-code fail), (6) student name/email fuzzy. Do **not** reorder design.md. Instead, Task 7's contract and tests must be brought into line with this order (the original plan draft had prefixes last and `external_ref` above the student code — that draft was wrong). If the external_ref/MSSV-collision note is missing from design.md, add only: "an exact `student_id` (MSSV) format always outranks an `external_ref` match."

- [ ] **Step 4: Mirror the decisions in `execplan.md` and `validation.md`**

In `execplan.md` Phase 4, name the `Support\Integrity\FinanceInvariantRegistry`/`FinanceIntegrityAuditor` extraction (command parity preserved, errors surfaced not swallowed) as the first backend work, and the `Support\Audit\` builders as the second.

In `validation.md`:
- Add a row: "Unit | Command parity: `finance:audit-invariants` produces identical counts **and still renders `ERROR` for a failing invariant** after the registry refactor."
- **Soften the export rows** (currently `:21` and `:25`) so they no longer require a live export endpoint. Rewrite row 21's export clause to: "export is **denied** without `export_finance_audit_workspace`; with the permission the disabled export affordance is shown — **the egress endpoint + audit entry are deferred to a follow-up slice**." Reword the `Logs/Audit` row 25 to: "When the export endpoint ships, its audit entries must include actor, target, scope, timestamp, and outcome (deferred this slice); runtime logs never include raw DNG payloads or sensitive PII."

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

/**
 * Subject scope for finance integrity checks. Every searched subject
 * (student/invoice/payment/DNG/charge) is resolved to its owning student id set
 * upstream (GetFinanceAuditGraphQuery), so the scope only carries student ids.
 * If a future invariant genuinely needs entity-level scoping, add the id type
 * here together with its registry predicate — do not add unused fields now.
 */
final class FinanceAuditScope
{
    /**
     * @param  list<int>  $studentIds
     */
    public function __construct(
        public readonly array $studentIds = [],
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
use App\Modules\Finance\Support\Integrity\FinanceInvariant;
use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;
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
     * Global run for the command. A failing invariant yields count=null + error
     * message (never 0) so the command can still render ERROR — parity preserved.
     *
     * @return list<array{code:string,severity:string,label:string,count:?int,error:?string}>
     */
    public function summarize(?FinanceAuditScope $scope = null): array
    {
        return array_map(function (FinanceInvariant $invariant) use ($scope): array {
            try {
                $count = $this->count($invariant, $scope);
                $error = null;
            } catch (Throwable $e) {
                $count = null;
                $error = $e->getMessage();
            }

            return [
                'code' => $invariant->code,
                'severity' => $invariant->severity,
                'label' => $invariant->label,
                'count' => $count,
                'error' => $error,
            ];
        }, $this->registry->all());
    }

    /**
     * Raw count. Deliberately does NOT swallow DB errors — a broken invariant SQL
     * must surface as a failure, not as "0 offending rows".
     */
    public function count(FinanceInvariant $invariant, ?FinanceAuditScope $scope = null): int
    {
        $sql = $this->resolve($invariant->countSqlTemplate, $invariant, $scope);

        return (int) (DB::selectOne($sql)->c ?? 0);
    }

    /**
     * Best-effort sample ids — only ever called after count() already succeeded,
     * so an empty list here is decoration loss, not a hidden integrity failure.
     *
     * @return list<int>
     */
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
     * Subject-scoped findings. Emits a finding for every invariant that is either
     * violated (kind=invariant) or failed to run (kind=invariant_error). Empty
     * scope returns [] (never a global scan).
     *
     * @return list<array{code:string,severity:string,label:string,kind:string,sample_ids:list<int>}>
     */
    public function findForScope(FinanceAuditScope $scope): array
    {
        if ($scope->isEmpty()) {
            return [];
        }

        $findings = [];
        foreach ($this->registry->all() as $invariant) {
            try {
                $count = $this->count($invariant, $scope);
            } catch (Throwable) {
                $findings[] = [
                    'code' => $invariant->code,
                    'severity' => 'ERROR',
                    'label' => $invariant->label,
                    'kind' => 'invariant_error',
                    'sample_ids' => [],
                ];

                continue;
            }

            if ($count > 0) {
                $findings[] = [
                    'code' => $invariant->code,
                    'severity' => $invariant->severity,
                    'label' => $invariant->label,
                    'kind' => 'invariant',
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

**Preserve the ERROR branch (parity):** the original `catch` renders `[code, severity, 'ERROR', substr(message, 0, 60)]` and does not add to `$totalBad`. With the auditor, a row whose `count === null` is that same error — render `'ERROR'` + `mb_substr($row['error'] ?? '', 0, 60)` and skip the `$totalBad` increment, exactly as before. Only rows with a non-null int count contribute the `✅ 0` / `❌ {n}` cell and feed `$totalBad`. Confirm `finance:audit-invariants` output is byte-identical for both clean and erroring invariants.

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

**Contract:** `handle(?string $q, ?int $campusId): array` returns the **Resolution** shape. Precedence — **exactly the order locked in `design.md:79-90`**: (1) explicit prefixes `payment:<id>` / `invoice:<id>` / `dng:<id>` / `charge:<id>` (always win when present); (2) exact `student_invoices.invoice_number`; (3) DNG `item_id` → `dng_payment_id` → `dng_transaction_id`; (4) exact `students.student_id` (MSSV); (5) `payment.external_ref`, **only after (2)–(4) fail and never when `$q` is MSSV-shaped** (an exact student code always outranks an external_ref); (6) student name/email fuzzy (`Student` search) → may be ambiguous. Bare integers never guess. **Every candidate is filtered to `$campusId`** (resolve the owning student's campus) before inclusion; cross-campus rows are invisible (treated as no-match, never "denied").

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

it('resolves an explicit prefix id directly, ahead of any free-form search', function () {
    $student = Student::factory()->forCampus($this->campus)->forProgram($this->program)->create();
    $invoice = StudentInvoice::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'invoice_number' => 'INV-PFX-001',
    ]);

    $result = app(ResolveFinanceAuditSearchQuery::class)->handle("invoice:{$invoice->id}", $this->campus->id);

    expect($result['status'])->toBe('single')
        ->and($result['target'])->toBe(['type' => 'invoice', 'id' => $invoice->id]);
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

Implement `handle()` walking the precedence list **top to bottom, returning on the first hit**, each lookup scoped to `$campusId` via the owning student's campus relation. The walk **starts** with prefix handling: if `$q` matches `^(payment|invoice|dng|charge):(\d+)$`, resolve that id directly (still campus-checked) and return before any free-form search. Only if no prefix is present fall through to (2) invoice_number, (3) DNG ids, (4) exact student code, (5) external_ref, (6) fuzzy. Define a private `looksLikeStudentCode(string $q): bool` (MSSV format — letters+digits, e.g. `^[A-Za-z]{2,}\d{2,}$`; confirm the real format against existing `students.student_id` data before finalizing the regex) and short-circuit the external_ref branch when it returns true. Return the Resolution shape. Build private `studentMatch`/`invoiceMatch`/etc. helpers to format `matches` rows (`label`, `sublabel`).

- [ ] **Step 4: Run to verify it passes**

Run: `./scripts/dev.sh test --filter=ResolveFinanceAuditSearchQueryTest`
Expected: PASS (6 tests).

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

## Task 9: `FinanceLedgerTimelineBuilder`

**Files:**
- Create: `app/Modules/Finance/Support/Audit/FinanceLedgerTimelineBuilder.php`
- Test: `tests/Unit/Finance/Audit/FinanceLedgerTimelineBuilderTest.php`

**Contract:** `build(array $graph): array` returns a chronological list of **Timeline event**s built from graph nodes/edges. Preserves signed amounts: a `payment_application` with `entry_type=reversal` (negative `amount`) becomes a `signed_amount < 0` event labelled "Payment reversal"; allocations/releases likewise. Pure function (no DB) — input is the graph from Task 8, so the graph must carry application/allocation entry rows. (If Task 8's node set lacks raw ledger entries, extend its output with a `ledger_entries` array — adjust both tasks to agree on that key.)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Audit\FinanceLedgerTimelineBuilder;

it('preserves signed reversal entries and orders by time', function () {
    $graph = [
        'ledger_entries' => [
            ['at' => '2026-01-02 10:00:00', 'type' => 'payment_application', 'amount' => -50.0, 'entry_type' => 'reversal', 'refs' => ['payment' => 1, 'invoice_line' => 9]],
            ['at' => '2026-01-01 09:00:00', 'type' => 'payment_application', 'amount' => 200.0, 'entry_type' => 'application', 'refs' => ['payment' => 1, 'invoice_line' => 9]],
        ],
    ];

    $timeline = (new FinanceLedgerTimelineBuilder())->build($graph);

    expect($timeline[0]['at'])->toBe('2026-01-01 09:00:00')
        ->and($timeline[0]['signed_amount'])->toBe(200.0)
        ->and($timeline[1]['signed_amount'])->toBe(-50.0)
        ->and($timeline[1]['label'])->toContain('reversal');
});
```

- [ ] **Step 2–5:** Run → FAIL; implement the pure transform; run → PASS; commit `feat(finance): add signed finance ledger timeline builder`.

---

## Task 10: `FinanceAuditWarningBuilder`

**Files:**
- Create: `app/Modules/Finance/Support/Audit/FinanceAuditWarningBuilder.php`
- Test: `tests/Feature/Finance/Audit/FinanceAuditWarningBuilderTest.php`

**Contract:** `build(FinanceAuditScope $scope): array` returns a list of **Warning**s. It (a) calls `FinanceIntegrityAuditor::findForScope($scope)` and **passes through each finding's `kind` verbatim** (`invariant` for a violation, `invariant_error` for a check that failed to run) — it must never relabel an `invariant_error` as a clean/ordinary invariant; and (b) computes `kind => 'cache_drift'` warnings via `SettlementService::deriveInvoiceSnapshot()` for each of the scope's student invoices where cached vs derived differ by > 0.01. Cache-drift uses `SettlementService` (canonical balance); INV-2 remains the command's DB guard — overlap is intentional and documented.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Audit\FinanceAuditWarningBuilder;
use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('emits a cache_drift warning when cached_paid_amount disagrees with SettlementService', function () {
    [$student, $invoice] = seedStaleCacheFixture(); // reuse helper shape from Task 8

    $warnings = app(FinanceAuditWarningBuilder::class)
        ->build(new FinanceAuditScope(studentIds: [$student->id]));

    expect(collect($warnings)->where('kind', 'cache_drift')->pluck('sample_ids')->flatten())
        ->toContain($invoice->id);
});

it('emits invariant warnings only for the scoped student', function () {
    $bad = makeStudentWithOverAllocatedPayment(); // reuse Task 4 helper
    $warnings = app(FinanceAuditWarningBuilder::class)
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

- [ ] **Step 1: Build the page** with: a universal search bar (`useForm` GET to `finance.audit.index` via route helper); a resolution area handling `empty` / `single` / `ambiguous` (ambiguous → clickable `matches`); subject header; `<Deferred>` blocks for graph summary, signed ledger timeline (render `signed_amount` with sign + color), derived-balance panel (cached vs derived, highlight `drift`), and warning panel (group by `severity`; render `kind=cache_drift` and `kind=invariant` distinctly, and surface `kind=invariant_error` as an "audit unavailable" notice — never as a passed/clean check); lightweight actions (copy authenticated link, refresh, open source page via `links`, export button **disabled** with tooltip). Follow web design-quality rules — intentional hierarchy, not a default card grid.

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

---

## Revision log (2026-06-15, pre-execution review)

Applied before execution after a structural review:
- **[P1] Scope = student-only.** `FinanceAuditScope` trimmed to `studentIds` (dead invoice/payment/charge/DNG id fields removed); every subject resolves to its owning student upstream. (T2)
- **[P1] No swallowed SQL errors.** `FinanceIntegrityAuditor` no longer returns `0`/`[]` on `Throwable`. `summarize()` yields `count=null`+`error`; `findForScope()` emits `kind=invariant_error`; command keeps its `ERROR` row (parity); workspace shows "audit unavailable". New error-state test added. (T4, T5, T10, T13, Warning shape)
- **[P1] Resolver precedence realigned to `design.md:79-90`** — explicit prefixes **first**, `external_ref` only after exact student code. Prefix-precedence test added. (T1, T7)
- **[P2] Export deferred + validation softened.** Permission seeded, endpoint/audit-log deferred; `validation.md` rows 21/25 reworded so they no longer assert a live export endpoint. (T1, T6)
- **[P2] Namespace convention locked.** Engine under `Support\Integrity`; workspace pure transforms under `Support\Audit` with repo noun+role names (`FinanceLedgerTimelineBuilder`, `FinanceAuditWarningBuilder`). `design.md` updated in T1. (T1, T9, T10)
- **[P3] Baseline + deps hardened.** Treat the memory baseline as a hint and re-capture in-worktree; symlink helper is detect-first, not a hardcoded path. (T0)
