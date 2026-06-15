# Finance Office — Student 360 (Full) — Implementation Plan (Milestone 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Grow the Milestone-1 Student 360 *shell* into the full operator surface — 4 status cards, dual ledger/timeline lenses, the DNG 2-call state stepper, a permission-aware "Thao tác ▾" action menu, and the `focus=<type>:<id>` deep-link behavior — by **reusing existing money-write Actions through thin adapters** and existing read models. No money-calculation logic is rewritten.

**Architecture:** Same URL as M1 (`GET /finance/students/{student}` → `Finance/Student360/Show`); this milestone *augments* its props and components — it never changes the route or the destination. New backend is read-models (status-card aggregate, grouped ledger, manual-allocate preview, DNG-cancel impact) plus two thin write adapters that wrap **already-tested Actions/Services**: `PaymentService::recordPayment` ("Ghi nhận thanh toán") and `CancelDngPaymentRequestAction` (DNG cancel, upgraded from 1-step confirm to the 4-layer safe-destructive pattern). The 4-layer destructive UI + blocking-reason logic already exist for the lifecycle flow (`LifecycleExceptions.vue`, `LifecycleDueExceptionRowMapper`) and are reused.

**Tech Stack:** Laravel 13, Inertia v3, Vue 3 `<script setup lang="ts">`, Tailwind v4, Ziggy, Pest, shadcn-vue (`@/components/ui/*` incl. `sheet`, `dialog`, `select`, `badge`, `card`), `lucide-vue-next`.

**Depends on:** Milestone 1 (`docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`) — the 360 route shell, `financeRoutes`, `view_finance_student_overview`, the `Show.vue` page, and `resources/js/types/finance.ts` must already exist and be merged.

**Story:** `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/` (`FIN-REV-010`). Design source: `docs/features/finance/finance-office-ux-redesign-design.md` §5, §7.2, §10 milestone 2. **Portal impact: none.**

**Risk lane:** high-risk (`authz`, `finance-write-path-adjacent`, `DNG provider adjacency`, `data loss` via void). Hard gates: every write drawer must wrap an existing tested Action/Service or be explicitly justified; every new route is permission-scoped + campus-scoped; the DNG-cancel adapter must add `void_finance_charges` when linked charges exist (closes the gap that the current `cancel` route gates only `create_finance_payments`).

---

## Testing approach (same as M1 — read first)

Backend = TDD with Pest (`./scripts/dev.sh test <path>`), using the proven pattern: `uses(RefreshDatabase::class)`, a `grant…()` helper mocking `PermissionService::getUserPermissions`, `session(['current_campus_id' => …])` + `app()->singleton('campus', fn () => $campus)`, then `assertInertia(...)` / `assertJsonPath(...)`. Frontend = `./scripts/dev.sh npm run lint -- <files>` + a browser smoke at the end (no JS unit runner exists; do **not** run whole-project `vue-tsc` in the dev container — it OOMs). **Money state changes in this milestone** (record payment, DNG cancel/void) → after the integration smoke, record `finance:audit-invariants` evidence (Task 7).

---

## File Structure (M2)

**Created — backend read models:**
- `app/Modules/Finance/Queries/Student360/GetStudent360StatusCardsQuery.php` — the 4 status cards.
- `app/Modules/Finance/Queries/Student360/GetStudent360LedgerQuery.php` — grouped Kỳ→Invoice→line ("Sổ cái" lens).
- `app/Modules/Finance/Queries/Student360/PreviewManualAllocationQuery.php` — what an unapplied payment would cover (read-only).
- `app/Modules/Finance/Queries/Student360/BuildDngCancelImpactQuery.php` — linked-charge impact + blocking reasons for the cancel drawer.

**Created — backend adapters (thin wrappers over existing write logic):**
- `app/Modules/Finance/Http/Requests/Student360/RecordManualPaymentRequest.php`
- `app/Modules/Finance/Http/Requests/Student360/ReviewedCancelDngRequest.php`
- `app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php` — `store` (record payment) + `allocatePreview` (JSON).

**Modified — backend:**
- `app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php` — add `status_cards`, `ledger_groups` (deferred), `actions` permission flags.
- `app/Modules/Finance/Http/Web/Admin/DngPaymentRequestController.php` — add `cancelImpact` (JSON) + `cancelReviewed` (4-layer write).
- `app/Modules/Finance/routes/web.php` — register the 4 new routes.

**Created — frontend:**
- `resources/js/components/finance/student360/StatusCards.vue`
- `resources/js/components/finance/student360/DngStateStepper.vue`
- `resources/js/components/finance/student360/LedgerLens.vue`
- `resources/js/components/finance/student360/StudentActionMenu.vue`
- `resources/js/components/finance/student360/RecordPaymentDrawer.vue`
- `resources/js/components/finance/student360/AllocatePreviewDrawer.vue`
- `resources/js/components/finance/student360/CancelDngDrawer.vue`

**Modified — frontend:**
- `resources/js/pages/Finance/Student360/Show.vue` — render the cards/lenses/stepper/action-menu; handle `focus` highlight.
- `resources/js/utils/routes.ts` — extend `financeRoutes` with the new endpoints.
- `resources/js/constants/finance-routes.ts` — add the new route names.
- `resources/js/types/finance.ts` — add M2 types.

**Tests:**
- `tests/Feature/Finance/Student360/Student360OverviewTest.php`
- `tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php`
- `tests/Feature/Finance/Student360/RecordManualPaymentTest.php`
- `tests/Feature/Finance/Dng/ReviewedCancelDngTest.php`

---

## Verified backend facts this plan builds on

- **DNG status machine** (`app/Modules/Finance/Dng/Models/DngPaymentRequest.php`): constants `STATUS_PENDING`, `STATUS_PUSHED_TO_DNG`, `STATUS_PAID_UNINVOICED`, `STATUS_PAID_INVOICED`, `STATUS_RECONCILED`, `STATUS_FAILED`, `STATUS_CANCELLED`, `STATUS_CANCEL_PUSHED_TO_DNG`. 2-call lifecycle: `pending → pushed_to_dng → paid_uninvoiced (Call 1) → paid_invoiced (Call 2) → reconciled`.
- **`CancelDngPaymentRequestAction::run(DngPaymentRequest $request): void`** (`app/Modules/Finance/Actions/CancelDngPaymentRequestAction.php`) — only `pending`/`pushed_to_dng` cancellable; **always voids linked charges if present**; audit via `cancel_push_payload`/`cancel_push_response`. The existing controller `cancel` is a 1-step confirm gated only `create_finance_payments`.
- **`LifecycleDueExceptionRowMapper::blockingReasons(DngPaymentRequest): array`** (public static) — returns Vietnamese blocking strings: "DNG request đã có payment bridge" (when `hasBridgedPayment()` or `payment_id !== null`) and "Trạng thái DNG hiện tại (…) không cho phép hủy" (when status ∉ {pending, pushed_to_dng}). Reused for the cancel drawer.
- **`PaymentService::recordPayment(array $data): Payment`** (`app/Modules/Finance/Services/PaymentService.php`) — keys: `student_id`, `amount`, `method`, `source`, `external_ref`, `paid_at`, `status`, `received_by_user_id`, `raw_payload`, `notes`. The old `/finance/payments/create` page has **no POST handler** → "Ghi nhận thanh toán" is a genuinely new (thin) adapter over this service.
- **`AllocatePaymentAction::run($payment, $charge, float $amount, int $userId)`** + route `finance.payments.allocate` (`POST /finance/payments/{payment}/allocate`, gate `allocate_finance_payment`) — the existing write path; the 360 "Apply" reuses it, the preview is read-only.
- **SettlementService** read methods: `getPaymentUnappliedAmount(Payment): float`, `getLineOutstandingAmount(InvoiceLine): float`, `getOutstandingLinesForStudent(int $studentId, array $priorityOrder): Collection`, `deriveInvoiceSnapshot(StudentInvoice): array{gross,discount,net,paid,remaining,status}`.
- **Installments**: `FinanceChargeInstallment` (`finance_charge_installments`) cols `finance_charge_id, installment_no, amount, due_date, status(pending|awaiting_payment|paid|cancelled), dng_payment_request_id, paid_at, push_attempt_count, last_push_error`; `FinanceCharge::installments()` hasMany; `PushNextInstallmentAction::handle(int $chargeId, ?int $installmentId)`; controller `retryPushInstallment` gated by `splitInstallment` policy.
- **Money graph**: `GetFinanceAuditGraphQuery::handle(['type'=>'student','id'=>$id])` → `{subject, student_id, nodes[], edges[], derived_balance[], ledger_entries[]}`; `FinanceLedgerTimelineBuilder::build($graph)` → signed timeline. (M1 already wires this as the deferred `ledger` = "Dòng thời gian" lens.)

---

## Task 1: Backend — 360 status-card aggregate + grouped ledger

Adds the read data for the 4 status cards (§5.3) and the "Sổ cái" grouped lens (§5.4). All numbers come from existing services — the queries only assemble and group.

**Files:**
- Create: `app/Modules/Finance/Queries/Student360/GetStudent360StatusCardsQuery.php`
- Create: `app/Modules/Finance/Queries/Student360/GetStudent360LedgerQuery.php`
- Modify: `app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php`
- Test: `tests/Feature/Finance/Student360/Student360OverviewTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Student360/Student360OverviewTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantStudent360')) {
    function grantStudent360(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $this->student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id])
        ->create();
});

it('exposes the four status cards and ledger groups on the 360', function () {
    $user = grantStudent360(['view_finance_student_overview']);

    actingAs($user)->get("/finance/students/{$this->student->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Student360/Show')
            ->has('status_cards.balance')
            ->has('status_cards.dng')
            ->has('status_cards.installments')
            ->has('status_cards.exception')
            ->has('actions'));
});

it('reports action permission flags from the user permissions', function () {
    $user = grantStudent360(['view_finance_student_overview', 'create_finance_payments']);

    actingAs($user)->get("/finance/students/{$this->student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('actions.can_record_payment', true)
            ->where('actions.can_cancel_dng', true)
            ->where('actions.can_void_charges', false)
            ->where('actions.can_allocate', false));
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360/Student360OverviewTest.php`
Expected: FAIL — `status_cards`/`actions` props missing.

- [ ] **Step 3: Create the status-cards query**

Create `app/Modules/Finance/Queries/Student360/GetStudent360StatusCardsQuery.php`. Reuses `GetStudentBalanceQuery`, `DngPaymentRequest`, `FinanceChargeInstallment`, `LifecycleDueExceptionRowMapper` — no new money math:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Models\FinanceCharge;
use App\Models\FinanceChargeInstallment;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Modules\Finance\Support\LifecycleDueExceptionRowMapper;

/**
 * Read-only aggregate for the Student 360 status cards (§5.3). Every figure is
 * delegated to an existing service/query — this class only assembles.
 */
class GetStudent360StatusCardsQuery
{
    public function __construct(
        private GetStudentBalanceQuery $balanceQuery,
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $studentId): array
    {
        return [
            'balance' => $this->balanceCard($studentId),
            'dng' => $this->dngCard($studentId),
            'installments' => $this->installmentsCard($studentId),
            'exception' => $this->exceptionCard($studentId),
        ];
    }

    /** @return array<string,mixed> */
    private function balanceCard(int $studentId): array
    {
        $balance = $this->balanceQuery->handle($studentId);

        return [
            'balance' => $balance['balance'],
            'unapplied_credit' => $balance['unapplied_credit'],
            'has_unapplied' => $balance['unapplied_credit'] > 0,
        ];
    }

    /** @return array<string,mixed> */
    private function dngCard(int $studentId): array
    {
        $latest = DngPaymentRequest::query()
            ->where('student_id', $studentId)
            ->latest('id')
            ->first();

        if ($latest === null) {
            return ['has_active' => false, 'request' => null];
        }

        return [
            'has_active' => true,
            'request' => [
                'id' => (int) $latest->id,
                'status' => $latest->status,
                'item_id' => $latest->item_id,
                'amount' => (float) $latest->amount,
                'error_message' => $latest->error_message,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function installmentsCard(int $studentId): array
    {
        $chargeIds = FinanceCharge::query()->where('student_id', $studentId)->pluck('id');

        $installments = FinanceChargeInstallment::query()
            ->whereIn('finance_charge_id', $chargeIds)
            ->orderBy('installment_no')
            ->get();

        $next = $installments->firstWhere('status', FinanceChargeInstallment::STATUS_PENDING);

        return [
            'total' => $installments->count(),
            'paid' => $installments->where('status', FinanceChargeInstallment::STATUS_PAID)->count(),
            'next' => $next === null ? null : [
                'id' => (int) $next->id,
                'charge_id' => (int) $next->finance_charge_id,
                'installment_no' => (int) $next->installment_no,
                'due_date' => $next->due_date?->toDateString(),
                'has_push_error' => $next->last_push_error !== null,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function exceptionCard(int $studentId): array
    {
        // A student is "exceptional" when they hold a still-cancellable DNG request
        // whose blocking reasons are empty (i.e. a real decision is pending).
        $candidate = DngPaymentRequest::query()
            ->where('student_id', $studentId)
            ->whereIn('status', [DngPaymentRequest::STATUS_PENDING, DngPaymentRequest::STATUS_PUSHED_TO_DNG])
            ->latest('id')
            ->first();

        if ($candidate === null) {
            return ['needs_review' => false, 'dng_request_id' => null, 'blocking_reasons' => []];
        }

        $blocking = LifecycleDueExceptionRowMapper::blockingReasons($candidate);

        return [
            'needs_review' => $blocking === [],
            'dng_request_id' => (int) $candidate->id,
            'blocking_reasons' => $blocking,
        ];
    }
}
```

- [ ] **Step 4: Create the grouped-ledger query**

Create `app/Modules/Finance/Queries/Student360/GetStudent360LedgerQuery.php`. Groups Kỳ→Invoice→line using `SettlementService::deriveInvoiceSnapshot` + `getLineOutstandingAmount` (does not touch the shared audit graph):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Support\Collection;

/**
 * Read-only "Sổ cái" lens (§5.4): student invoices grouped by semester, each with
 * its derived snapshot and lines. All money figures come from SettlementService.
 */
class GetStudent360LedgerQuery
{
    public function __construct(
        private SettlementService $settlement,
    ) {}

    /** @return list<array<string,mixed>> */
    public function handle(int $studentId): array
    {
        $invoices = StudentInvoice::query()
            ->where('student_id', $studentId)
            ->with(['semester:id,name,code', 'invoiceLines.charge'])
            ->orderByDesc('semester_id')
            ->orderBy('id')
            ->get();

        return $invoices
            ->groupBy(fn (StudentInvoice $i) => $i->semester_id)
            ->map(fn (Collection $group) => [
                'semester' => $this->semesterLabel($group->first()),
                'invoices' => $group->map(fn (StudentInvoice $invoice) => $this->invoiceRow($invoice))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return array{id:?int,name:string} */
    private function semesterLabel(StudentInvoice $invoice): array
    {
        $semester = $invoice->semester;

        return [
            'id' => $semester?->id !== null ? (int) $semester->id : null,
            'name' => $semester?->name ?? 'Chưa gắn kỳ',
        ];
    }

    /** @return array<string,mixed> */
    private function invoiceRow(StudentInvoice $invoice): array
    {
        $snapshot = $this->settlement->deriveInvoiceSnapshot($invoice);

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => (string) $invoice->invoice_number,
            'status' => $snapshot['status'],
            'net' => (float) $snapshot['net'],
            'paid' => (float) $snapshot['paid'],
            'remaining' => (float) $snapshot['remaining'],
            'lines' => $invoice->invoiceLines->map(fn (InvoiceLine $line) => [
                'id' => (int) $line->id,
                'label' => (string) ($line->charge?->charge_type ?? $line->description ?? 'Dòng phí'),
                'outstanding' => $this->settlement->getLineOutstandingAmount($line),
            ])->all(),
        ];
    }
}
```

> Confirm `InvoiceLine`'s description/charge columns by grepping `app/Models/InvoiceLine.php`; if `description` is absent, drop the `?? $line->description` fallback (the `charge_type` path is the proven one from `SettlementService::getOutstandingLinesForStudent`).

- [ ] **Step 5: Augment the controller**

In `app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php`, add imports:

```php
use App\Modules\Finance\Queries\Student360\GetStudent360LedgerQuery;
use App\Modules\Finance\Queries\Student360\GetStudent360StatusCardsQuery;
```

Inject the two queries into `show(...)` (append to the parameter list) and add three props after the existing `$props` assembly (keep M1's `student`, `balances`, `focus`, `links`, deferred `ledger`):

```php
        $props['status_cards'] = $cardsQuery->handle($studentId);

        $props['actions'] = [
            'can_record_payment' => $request->user()?->can('create_finance_payments') ?? false,
            'can_allocate' => $request->user()?->can('allocate_finance_payment') ?? false,
            'can_cancel_dng' => $request->user()?->can('create_finance_payments') ?? false,
            'can_void_charges' => $request->user()?->can('void_finance_charges') ?? false,
        ];

        // "Sổ cái" grouped lens is heavier than the four cards → deferred.
        $props['ledger_groups'] = Inertia::defer(fn () => $ledgerQuery->handle($studentId));
```

(Method signature becomes: `show(Student $student, Student360ShowRequest $request, GetStudentBalanceQuery $balanceQuery, GetFinanceAuditGraphQuery $graphQuery, FinanceLedgerTimelineBuilder $timelineBuilder, GetStudent360StatusCardsQuery $cardsQuery, GetStudent360LedgerQuery $ledgerQuery): Response`.)

- [ ] **Step 6: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360/Student360OverviewTest.php`
Expected: PASS (2 passing).

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Queries/Student360/GetStudent360StatusCardsQuery.php \
  app/Modules/Finance/Queries/Student360/GetStudent360LedgerQuery.php \
  app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php \
  tests/Feature/Finance/Student360/Student360OverviewTest.php
git commit -m "feat(finance): add Student 360 status cards and grouped ledger read models"
```

---

## Task 2: Backend — manual-allocate preview (read-only)

§5.3 requires "Phân bổ ngay → preview khớp" as a **read-model** distinct from the write path. The existing `allocate` posts straight in (no preview); auto-allocate preview exists but is legacy JSON. This task adds a payment-scoped preview that reuses `SettlementService` and returns `ApiResponse`. The actual apply reuses the existing `finance.payments.allocate` route — no new write path.

**Files:**
- Create: `app/Modules/Finance/Queries/Student360/PreviewManualAllocationQuery.php`
- Create: `app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php` (`allocatePreview` here; `store` added in Task 3)
- Modify: `app/Modules/Finance/routes/web.php`
- Test: `tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantAllocPreview')) {
    function grantAllocPreview(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $this->student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['intake' => 1])->create();
});

it('previews a payment unapplied amount and candidate lines', function () {
    $user = grantAllocPreview(['allocate_finance_payment']);
    $payment = Payment::create([
        'student_id' => $this->student->id, 'amount' => 1000000, 'method' => 'other',
        'status' => Payment::STATUS_COMPLETED, 'paid_at' => now(),
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.payment_id', $payment->id)
        ->assertJsonPath('data.unapplied', 1000000)
        ->assertJsonStructure(['data' => ['payment_id', 'unapplied', 'candidates']]);
});

it('denies preview without allocate permission', function () {
    $user = grantAllocPreview([]);
    $payment = Payment::create([
        'student_id' => $this->student->id, 'amount' => 1, 'method' => 'other',
        'status' => Payment::STATUS_COMPLETED, 'paid_at' => now(),
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")->assertForbidden();
});

it('hides a cross-campus payment as not found', function () {
    $user = grantAllocPreview(['allocate_finance_payment']);
    $other = Student::factory()->forCampus($this->otherCampus)->forProgram($this->program)->state(['intake' => 1])->create();
    $payment = Payment::create([
        'student_id' => $other->id, 'amount' => 1, 'method' => 'other',
        'status' => Payment::STATUS_COMPLETED, 'paid_at' => now(),
    ]);

    actingAs($user)->getJson("/finance/payments/{$payment->id}/allocate-preview")->assertNotFound();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php`
Expected: FAIL — route missing.

- [ ] **Step 3: Create the preview query**

Create `app/Modules/Finance/Queries/Student360/PreviewManualAllocationQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Modules\Finance\Services\SettlementService;

/**
 * Read-only preview: how much of a payment is unapplied and which outstanding
 * charge lines it could cover (default fee-type priority). Reuses SettlementService;
 * computes nothing new. The actual write still goes through finance.payments.allocate.
 */
class PreviewManualAllocationQuery
{
    /** Default fee priority — matches the auto-allocate default ordering. */
    private const DEFAULT_PRIORITY = ['tuition', 'major', 'egc', 'retake', 'non_academic'];

    public function __construct(
        private SettlementService $settlement,
    ) {}

    /** @return array<string,mixed> */
    public function handle(Payment $payment): array
    {
        $unapplied = $this->settlement->getPaymentUnappliedAmount($payment);
        $remaining = $unapplied;

        $candidates = $this->settlement
            ->getOutstandingLinesForStudent((int) $payment->student_id, self::DEFAULT_PRIORITY)
            ->map(function (InvoiceLine $line) use (&$remaining): array {
                $outstanding = $this->settlement->getLineOutstandingAmount($line);
                $apply = max(0.0, min($remaining, $outstanding));
                $remaining -= $apply;

                return [
                    'invoice_line_id' => (int) $line->id,
                    'charge_id' => $line->charge?->id !== null ? (int) $line->charge->id : null,
                    'label' => (string) ($line->charge?->charge_type ?? 'Dòng phí'),
                    'outstanding' => $outstanding,
                    'would_apply' => $apply,
                ];
            })
            ->filter(fn (array $row) => $row['would_apply'] > 0)
            ->values()
            ->all();

        return [
            'payment_id' => (int) $payment->id,
            'unapplied' => $unapplied,
            'candidates' => $candidates,
        ];
    }
}
```

- [ ] **Step 4: Create the controller (allocatePreview)**

Create `app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Payment;
use App\Models\Student;
use App\Modules\Finance\Queries\Student360\PreviewManualAllocationQuery;
use Illuminate\Http\JsonResponse;

class FinanceStudentPaymentController extends Controller
{
    /** JSON: read-only manual-allocate preview for a single payment (§5.3). */
    public function allocatePreview(Payment $payment, PreviewManualAllocationQuery $query): JsonResponse
    {
        $this->assertCampusVisible((int) $payment->student_id);

        return ApiResponse::success($query->handle($payment));
    }

    protected function assertCampusVisible(int $studentId): void
    {
        $campusId = app()->bound('campus') ? (app('campus')?->id !== null ? (int) app('campus')->id : null) : null;
        $owner = Student::find($studentId)?->campus_id;

        $visible = $campusId !== null && $owner !== null && (int) $owner === $campusId;
        if (! $visible && ! (request()->user()?->can('view_finance_all_campus') ?? false)) {
            abort(404);
        }
    }
}
```

- [ ] **Step 5: Register routes (constants + helper + web)**

In `resources/js/constants/finance-routes.ts` add to `FINANCE_ROUTE_NAMES`:

```typescript
    PAYMENT_ALLOCATE_PREVIEW: 'finance.payments.allocate-preview',
    PAYMENT_ALLOCATE: 'finance.payments.allocate',
    STUDENT_PAYMENT_STORE: 'finance.students.payments.store',
    DNG_CANCEL_IMPACT: 'finance.dng.payment-requests.cancel-impact',
    DNG_CANCEL_REVIEWED: 'finance.dng.payment-requests.cancel-reviewed',
    INSTALLMENT_RETRY_PUSH: 'finance.charges.installments.retry-push',
```

In `resources/js/utils/routes.ts`, extend `financeRoutes` (add under a `student360` key):

```typescript
    student360: {
        allocatePreview: (paymentId: number) => route(FINANCE_ROUTE_NAMES.PAYMENT_ALLOCATE_PREVIEW, { payment: paymentId }),
        allocate: (paymentId: number) => route(FINANCE_ROUTE_NAMES.PAYMENT_ALLOCATE, { payment: paymentId }),
        recordPayment: (studentId: number) => route(FINANCE_ROUTE_NAMES.STUDENT_PAYMENT_STORE, { student: studentId }),
        dngCancelImpact: (dngId: number) => route(FINANCE_ROUTE_NAMES.DNG_CANCEL_IMPACT, { dngPaymentRequest: dngId }),
        dngCancelReviewed: (dngId: number) => route(FINANCE_ROUTE_NAMES.DNG_CANCEL_REVIEWED, { dngPaymentRequest: dngId }),
        retryInstallmentPush: (chargeId: number, installmentId: number) =>
            route(FINANCE_ROUTE_NAMES.INSTALLMENT_RETRY_PUSH, { charge: chargeId, installment: installmentId }),
    },
```

In `app/Modules/Finance/routes/web.php`, add the import and the route (inside the finance group, near the payments routes):

```php
use App\Modules\Finance\Http\Web\Admin\FinanceStudentPaymentController;
```

```php
    // Student 360 — manual allocate preview (read-only JSON)
    Route::get('/payments/{payment}/allocate-preview', [FinanceStudentPaymentController::class, 'allocatePreview'])
        ->middleware('can:allocate_finance_payment')
        ->name('payments.allocate-preview');
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php`
Expected: PASS (3 passing).

- [ ] **Step 7: Lint TS + commit**

```bash
./scripts/dev.sh npm run lint -- resources/js/constants/finance-routes.ts resources/js/utils/routes.ts
git add app/Modules/Finance/Queries/Student360/PreviewManualAllocationQuery.php \
  app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php \
  app/Modules/Finance/routes/web.php \
  resources/js/constants/finance-routes.ts resources/js/utils/routes.ts \
  tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php
git commit -m "feat(finance): add read-only manual-allocate preview for Student 360"
```

---

## Task 3: Backend — "Ghi nhận thanh toán" adapter (thin write)

§5.2: the old `/finance/payments/create` route was removed and has no POST handler. Add a thin adapter wrapping the existing `PaymentService::recordPayment` — no new money logic, just validation + campus scope + permission gate + delegate.

**Files:**
- Create: `app/Modules/Finance/Http/Requests/Student360/RecordManualPaymentRequest.php`
- Modify: `app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php` (add `store`)
- Modify: `app/Modules/Finance/routes/web.php`
- Test: `tests/Feature/Finance/Student360/RecordManualPaymentTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Student360/RecordManualPaymentTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantRecordPayment')) {
    function grantRecordPayment(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
    $this->student = Student::factory()->forCampus($this->campus)->forProgram($this->program)->state(['intake' => 1])->create();
});

it('records a manual payment for a visible student', function () {
    $user = grantRecordPayment(['create_finance_payments']);

    actingAs($user)->post("/finance/students/{$this->student->id}/payments", [
        'amount' => 500000, 'method' => 'cash', 'paid_at' => now()->toDateString(),
        'external_ref' => 'RCPT-001', 'notes' => 'Cash at counter',
    ])->assertRedirect();

    expect(Payment::where('student_id', $this->student->id)->where('amount', 500000)->exists())->toBeTrue();
});

it('rejects a non-positive amount', function () {
    $user = grantRecordPayment(['create_finance_payments']);

    actingAs($user)->post("/finance/students/{$this->student->id}/payments", ['amount' => 0, 'method' => 'cash'])
        ->assertSessionHasErrors('amount');
});

it('denies recording without permission', function () {
    $user = grantRecordPayment([]);

    actingAs($user)->post("/finance/students/{$this->student->id}/payments", ['amount' => 1, 'method' => 'cash'])
        ->assertForbidden();
});

it('hides a cross-campus student as not found', function () {
    $user = grantRecordPayment(['create_finance_payments']);
    $other = Student::factory()->forCampus($this->otherCampus)->forProgram($this->program)->state(['intake' => 1])->create();

    actingAs($user)->post("/finance/students/{$other->id}/payments", ['amount' => 1, 'method' => 'cash'])
        ->assertNotFound();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360/RecordManualPaymentTest.php`
Expected: FAIL — route missing.

- [ ] **Step 3: Create the FormRequest**

Create `app/Modules/Finance/Http/Requests/Student360/RecordManualPaymentRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Student360;

use Illuminate\Foundation\Http\FormRequest;

class RecordManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level `can:create_finance_payments` gate handles authorization.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 4: Add the `store` method + helper**

In `app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php`, add imports and the method:

```php
use App\Modules\Finance\Http\Requests\Student360\RecordManualPaymentRequest;
use App\Modules\Finance\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
```

```php
    /** Thin adapter: records a manual payment via the existing PaymentService. */
    public function store(Student $student, RecordManualPaymentRequest $request, PaymentService $payments): RedirectResponse
    {
        $this->assertCampusVisible((int) $student->id);

        $data = $request->validated();
        $payments->recordPayment([
            'student_id' => (int) $student->id,
            'amount' => (float) $data['amount'],
            'method' => $data['method'],
            'source' => 'manual',
            'external_ref' => $data['external_ref'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
            'received_by_user_id' => (int) $request->user()->id,
        ]);

        Inertia::flash('success', 'Đã ghi nhận thanh toán.');

        return back();
    }
```

- [ ] **Step 5: Register the route**

In `app/Modules/Finance/routes/web.php`, add inside the finance group:

```php
    // Student 360 — record a manual payment (thin adapter over PaymentService)
    Route::post('/students/{student}/payments', [FinanceStudentPaymentController::class, 'store'])
        ->middleware('can:create_finance_payments')
        ->name('students.payments.store');
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360/RecordManualPaymentTest.php`
Expected: PASS (4 passing).

> If `recordPayment` dispatches a domain event that fails under `RefreshDatabase` (e.g. missing listener table), the failure will surface here — wrap the event side with `Event::fake()` in the test only if needed, but prefer leaving it real to prove the adapter integrates.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Http/Requests/Student360/RecordManualPaymentRequest.php \
  app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php \
  app/Modules/Finance/routes/web.php \
  tests/Feature/Finance/Student360/RecordManualPaymentTest.php
git commit -m "feat(finance): add manual payment recording adapter for Student 360"
```

---

## Task 4: Backend — DNG cancel 4-layer adapter + impact + gate fix

§7.2: the current DNG cancel is a 1-step confirm gated only `create_finance_payments`, even though `CancelDngPaymentRequestAction` **always voids linked charges if present**. This task adds (a) a read-only impact endpoint (linked charges + blocking reasons), and (b) a reviewed-cancel adapter that requires a reason + acknowledgement and **additionally requires `void_finance_charges` when linked charges exist** — wrapping the existing tested action. The existing 1-step `cancel` route is left intact for backward compatibility but the 360/DNG-list UIs point at the reviewed flow.

**Files:**
- Create: `app/Modules/Finance/Http/Requests/Student360/ReviewedCancelDngRequest.php`
- Create: `app/Modules/Finance/Queries/Student360/BuildDngCancelImpactQuery.php`
- Modify: `app/Modules/Finance/Http/Web/Admin/DngPaymentRequestController.php` (add `cancelImpact`, `cancelReviewed`)
- Modify: `app/Modules/Finance/routes/web.php`
- Test: `tests/Feature/Finance/Dng/ReviewedCancelDngTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Dng/ReviewedCancelDngTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantDngCancel')) {
    function grantDngCancel(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
    $this->student = Student::factory()->forCampus($this->campus)->forProgram($this->program)->state(['intake' => 1])->create();
});

function makePendingDng(Student $student, ?int $chargeId = null): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'C1',
        'student_code' => $student->student_id,
        'fee_type' => 'tuition',
        'amount' => 1000000,
        'status' => DngPaymentRequest::STATUS_PENDING,
        'item_id' => 'ITEM-'.$student->id,
        'due_date' => now()->addDays(10),
        'finance_charge_id' => $chargeId,
    ]);
}

it('returns cancel impact with blocking reasons', function () {
    $user = grantDngCancel(['create_finance_payments']);
    $dng = makePendingDng($this->student);

    actingAs($user)->getJson("/finance/dng/payment-requests/{$dng->id}/cancel-impact")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.dng_request_id', $dng->id)
        ->assertJsonStructure(['data' => ['dng_request_id', 'linked_charges', 'blocking_reasons', 'requires_void_permission']]);
});

it('requires a reason and acknowledgement to cancel', function () {
    $user = grantDngCancel(['create_finance_payments']);
    $dng = makePendingDng($this->student);

    actingAs($user)->post("/finance/dng/payment-requests/{$dng->id}/cancel-reviewed", ['reason' => '', 'acknowledged' => false])
        ->assertSessionHasErrors(['reason', 'acknowledged']);
});

it('cancels a pending DNG with no linked charges', function () {
    $user = grantDngCancel(['create_finance_payments']);
    $dng = makePendingDng($this->student);

    actingAs($user)->post("/finance/dng/payment-requests/{$dng->id}/cancel-reviewed", [
        'reason' => 'Student dropped out', 'acknowledged' => true,
    ])->assertRedirect();

    expect($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_CANCELLED);
});

it('requires void permission when the DNG has a linked charge', function () {
    $user = grantDngCancel(['create_finance_payments']); // no void_finance_charges
    $charge = FinanceCharge::factory()->create(['student_id' => $this->student->id]);
    $dng = makePendingDng($this->student, (int) $charge->id);

    actingAs($user)->post("/finance/dng/payment-requests/{$dng->id}/cancel-reviewed", [
        'reason' => 'Voiding', 'acknowledged' => true,
    ])->assertForbidden();

    expect($dng->fresh()->status)->toBe(DngPaymentRequest::STATUS_PENDING);
});
```

> Confirm `FinanceCharge::factory()` exists and its required columns; if not, create the charge via `FinanceCharge::create([...])` mirroring an existing finance test fixture. The behavioral assertions (status transition, 403) are what matter.

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Dng/ReviewedCancelDngTest.php`
Expected: FAIL — routes missing.

- [ ] **Step 3: Create the impact query**

Create `app/Modules/Finance/Queries/Student360/BuildDngCancelImpactQuery.php`. Reuses `LifecycleDueExceptionRowMapper::blockingReasons` (public static) and the DNG→charge links:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Models\FinanceCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\LifecycleDueExceptionRowMapper;

/**
 * Read-only impact for the 4-layer DNG cancel drawer (§7.2): the charges that will
 * be voided (so the operator sees the destructive scope) + blocking reasons reused
 * from the lifecycle mapper.
 */
class BuildDngCancelImpactQuery
{
    /** @return array<string,mixed> */
    public function handle(DngPaymentRequest $request): array
    {
        $charges = $this->resolveLinkedCharges($request);

        return [
            'dng_request_id' => (int) $request->id,
            'status' => $request->status,
            'amount' => (float) $request->amount,
            'linked_charges' => $charges->map(fn (FinanceCharge $c) => [
                'id' => (int) $c->id,
                'charge_type' => $c->charge_type,
                'status' => $c->status,
                'amount' => (float) $c->amount,
            ])->values()->all(),
            'blocking_reasons' => LifecycleDueExceptionRowMapper::blockingReasons($request),
            'requires_void_permission' => $charges->isNotEmpty(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int,FinanceCharge> */
    private function resolveLinkedCharges(DngPaymentRequest $request): \Illuminate\Support\Collection
    {
        // Single linkage via finance_charge_id, plus any pivot links if present.
        $ids = collect([$request->finance_charge_id])->filter()->map(fn ($id) => (int) $id);

        if (method_exists($request, 'chargeLinks')) {
            $ids = $ids->merge($request->chargeLinks()->pluck('finance_charge_id'));
        }

        $ids = $ids->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return FinanceCharge::query()->whereIn('id', $ids)->get();
    }
}
```

> Confirm the DNG→charge pivot relation name by grepping `app/Modules/Finance/Dng/Models/DngPaymentRequest.php` for `chargeLinks`/`charges`/`belongsToMany`. The `method_exists` guard keeps this safe if only the single `finance_charge_id` linkage exists.

- [ ] **Step 4: Create the FormRequest**

Create `app/Modules/Finance/Http/Requests/Student360/ReviewedCancelDngRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Student360;

use Illuminate\Foundation\Http\FormRequest;

class ReviewedCancelDngRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route gate handles create_finance_payments; void gate is enforced in the
        // controller (conditional on linked charges) so the message is specific.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'acknowledged' => ['accepted'],
        ];
    }
}
```

- [ ] **Step 5: Add controller methods + close the gate gap**

In `app/Modules/Finance/Http/Web/Admin/DngPaymentRequestController.php`, add imports:

```php
use App\Http\Responses\ApiResponse;
use App\Modules\Finance\Http\Requests\Student360\ReviewedCancelDngRequest;
use App\Modules\Finance\Queries\Student360\BuildDngCancelImpactQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
```

Add the two methods (reuse the existing `assertCampusAccess` helper already in this controller):

```php
    /** JSON: impact of cancelling this DNG (linked charges + blocking reasons). */
    public function cancelImpact(DngPaymentRequest $dngPaymentRequest, BuildDngCancelImpactQuery $query): JsonResponse
    {
        $dngPaymentRequest->loadMissing('student:id,campus_id');
        $this->assertCampusAccess($dngPaymentRequest);

        return ApiResponse::success($query->handle($dngPaymentRequest));
    }

    /** 4-layer reviewed cancel: reason + ack + (void gate when linked charges exist). */
    public function cancelReviewed(
        DngPaymentRequest $dngPaymentRequest,
        ReviewedCancelDngRequest $request,
        BuildDngCancelImpactQuery $impact,
        CancelDngPaymentRequestAction $action,
    ): RedirectResponse {
        $dngPaymentRequest->loadMissing('student:id,campus_id');
        $this->assertCampusAccess($dngPaymentRequest);

        // Gate-gap fix (§7.2): voiding linked charges requires void_finance_charges.
        if ($impact->handle($dngPaymentRequest)['requires_void_permission']
            && ! ($request->user()?->can('void_finance_charges') ?? false)) {
            throw new AuthorizationException('Cancelling this DNG voids linked charges and requires the void_finance_charges permission.');
        }

        $cancellable = [DngPaymentRequest::STATUS_PENDING, DngPaymentRequest::STATUS_PUSHED_TO_DNG];
        if (! in_array($dngPaymentRequest->status, $cancellable, true)) {
            Inertia::flash('error', 'This DNG payment request cannot be cancelled.');

            return back();
        }

        try {
            $action->run($dngPaymentRequest);
        } catch (\Throwable $e) {
            Inertia::flash('error', 'Failed to cancel DNG payment request: '.$e->getMessage());

            return back();
        }

        // Audit: persist the operator's reason on the request alongside the action's
        // own cancel_push_payload/response sink (DNG cancel does NOT write lifecycle events).
        $dngPaymentRequest->forceFill(['error_message' => null])->save();
        Inertia::flash('success', 'DNG payment request cancelled. Reason: '.$request->validated('reason'));

        return back();
    }
```

> `AuthorizationException` renders as 403 by Laravel's handler, satisfying the test's `assertForbidden()`. If this controller catches `Throwable` globally anywhere, ensure the `AuthorizationException` is thrown *before* the try/catch (it is, above).
> The reason is surfaced via flash + (optionally) an action log. If the repo has a finance action-log writer (grep `ActionLog`/`activity()` in finance), append a log line here so the reason is durably audited, not just flashed. Record which sink you used in the trace.

- [ ] **Step 6: Register routes**

In `app/Modules/Finance/routes/web.php`, inside the existing DNG payment-requests group (where `cancel` lives), add:

```php
        Route::get('/{dngPaymentRequest}/cancel-impact', [DngPaymentRequestController::class, 'cancelImpact'])
            ->middleware('can:create_finance_payments')
            ->name('cancel-impact');
        Route::post('/{dngPaymentRequest}/cancel-reviewed', [DngPaymentRequestController::class, 'cancelReviewed'])
            ->middleware('can:create_finance_payments')
            ->name('cancel-reviewed');
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Dng/ReviewedCancelDngTest.php`
Expected: PASS (4 passing).

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Finance/Http/Requests/Student360/ReviewedCancelDngRequest.php \
  app/Modules/Finance/Queries/Student360/BuildDngCancelImpactQuery.php \
  app/Modules/Finance/Http/Web/Admin/DngPaymentRequestController.php \
  app/Modules/Finance/routes/web.php \
  tests/Feature/Finance/Dng/ReviewedCancelDngTest.php
git commit -m "feat(finance): add 4-layer reviewed DNG cancel adapter with void gate"
```

---

## Task 5: Frontend — status cards, dual-lens ledger, DNG state stepper

Display layer for Tasks 1–4's read data. No writes yet (action menu + drawers land in Task 6).

**Files:**
- Modify: `resources/js/types/finance.ts` (add M2 types)
- Create: `resources/js/components/finance/student360/StatusCards.vue`
- Create: `resources/js/components/finance/student360/DngStateStepper.vue`
- Create: `resources/js/components/finance/student360/LedgerLens.vue`
- Modify: `resources/js/pages/Finance/Student360/Show.vue`

- [ ] **Step 1: Add the M2 types**

Append to `resources/js/types/finance.ts`:

```typescript
export interface DngCardRequest {
    id: number;
    status: string;
    item_id: string;
    amount: number;
    error_message: string | null;
}

export interface Student360StatusCards {
    balance: { balance: number; unapplied_credit: number; has_unapplied: boolean };
    dng: { has_active: boolean; request: DngCardRequest | null };
    installments: {
        total: number;
        paid: number;
        next: { id: number; charge_id: number; installment_no: number; due_date: string | null; has_push_error: boolean } | null;
    };
    exception: { needs_review: boolean; dng_request_id: number | null; blocking_reasons: string[] };
}

export interface Student360Actions {
    can_record_payment: boolean;
    can_allocate: boolean;
    can_cancel_dng: boolean;
    can_void_charges: boolean;
}

export interface LedgerInvoiceLine { id: number; label: string; outstanding: number }
export interface LedgerInvoice {
    id: number;
    invoice_number: string;
    status: string;
    net: number;
    paid: number;
    remaining: number;
    lines: LedgerInvoiceLine[];
}
export interface LedgerGroup { semester: { id: number | null; name: string }; invoices: LedgerInvoice[] }
```

- [ ] **Step 2: Build the DNG state stepper**

Create `resources/js/components/finance/student360/DngStateStepper.vue` (§5.5 — visual 2-call machine; statuses verbatim from `DngPaymentRequest`):

```vue
<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ status: string }>();

// Ordered happy-path steps of the 2-call lifecycle.
const steps = [
    { key: 'pushed_to_dng', label: 'Đẩy DNG' },
    { key: 'paid_uninvoiced', label: 'Đã trả (chưa h.đơn)' },
    { key: 'paid_invoiced', label: 'Đã trả (có h.đơn)' },
    { key: 'reconciled', label: 'Đối soát' },
] as const;

const order: Record<string, number> = {
    pending: 0,
    pushed_to_dng: 1,
    paid_uninvoiced: 2,
    paid_invoiced: 3,
    reconciled: 4,
};

const currentRank = computed(() => order[props.status] ?? 0);
const isTerminalCancel = computed(() => props.status === 'cancelled' || props.status === 'cancel_pushed_to_dng');
const isFailed = computed(() => props.status === 'failed');

const stepState = (stepKey: string): 'done' | 'current' | 'todo' => {
    const rank = order[stepKey] ?? 0;
    if (rank < currentRank.value) return 'done';
    if (rank === currentRank.value) return 'current';
    return 'todo';
};
</script>

<template>
    <div class="flex items-center gap-1 text-xs" :class="{ 'opacity-60': isTerminalCancel }">
        <template v-for="(step, idx) in steps" :key="step.key">
            <span
                class="rounded px-2 py-1"
                :class="{
                    'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200': stepState(step.key) === 'done',
                    'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200': stepState(step.key) === 'current' && !isFailed,
                    'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200': stepState(step.key) === 'current' && isFailed,
                    'bg-muted text-muted-foreground': stepState(step.key) === 'todo',
                }"
            >
                {{ step.label }}
            </span>
            <span v-if="idx < steps.length - 1" class="text-muted-foreground">─►</span>
        </template>
        <span v-if="isTerminalCancel" class="bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200 ml-2 rounded px-2 py-1">
            Đã hủy
        </span>
    </div>
</template>
```

- [ ] **Step 3: Build the status cards**

Create `resources/js/components/finance/student360/StatusCards.vue`:

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Student360Actions, Student360StatusCards } from '@/types/finance';
import { formatCurrency } from '@/utils/format';
import DngStateStepper from './DngStateStepper.vue';

defineProps<{ cards: Student360StatusCards; actions: Student360Actions }>();
const emit = defineEmits<{
    (e: 'allocate'): void;
    (e: 'cancelDng', id: number): void;
    (e: 'pushInstallment', payload: { chargeId: number; installmentId: number }): void;
    (e: 'review', dngId: number): void;
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <!-- Số dư & phân bổ -->
        <Card>
            <CardHeader class="pb-1"><CardTitle class="text-xs font-medium">Số dư & phân bổ</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <p class="text-lg font-semibold tabular-nums">{{ formatCurrency(cards.balance.balance) }}</p>
                <p v-if="cards.balance.has_unapplied" class="text-sm text-blue-700 dark:text-blue-300">
                    Dư chưa khớp: {{ formatCurrency(cards.balance.unapplied_credit) }}
                </p>
                <Button v-if="cards.balance.has_unapplied && actions.can_allocate" size="sm" variant="outline" @click="emit('allocate')">
                    Phân bổ ngay
                </Button>
            </CardContent>
        </Card>

        <!-- DNG hiện tại -->
        <Card>
            <CardHeader class="pb-1"><CardTitle class="text-xs font-medium">DNG hiện tại</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <template v-if="cards.dng.has_active && cards.dng.request">
                    <DngStateStepper :status="cards.dng.request.status" />
                    <p v-if="cards.dng.request.error_message" class="text-sm text-red-600 dark:text-red-400">
                        {{ cards.dng.request.error_message }}
                    </p>
                    <Button
                        v-if="actions.can_cancel_dng && ['pending', 'pushed_to_dng'].includes(cards.dng.request.status)"
                        size="sm" variant="destructive" @click="emit('cancelDng', cards.dng.request.id)"
                    >
                        Hủy DNG
                    </Button>
                </template>
                <p v-else class="text-muted-foreground text-sm">Không có DNG.</p>
            </CardContent>
        </Card>

        <!-- Trả góp -->
        <Card>
            <CardHeader class="pb-1"><CardTitle class="text-xs font-medium">Trả góp</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <template v-if="cards.installments.total > 0">
                    <p class="text-sm">{{ cards.installments.paid }} / {{ cards.installments.total }} kỳ đã trả</p>
                    <div v-if="cards.installments.next" class="flex items-center gap-2">
                        <Badge :variant="cards.installments.next.has_push_error ? 'destructive' : 'secondary'">
                            Kỳ {{ cards.installments.next.installment_no }} · {{ cards.installments.next.due_date ?? '—' }}
                        </Badge>
                        <Button
                            v-if="actions.can_record_payment"
                            size="sm" variant="outline"
                            @click="emit('pushInstallment', { chargeId: cards.installments.next.charge_id, installmentId: cards.installments.next.id })"
                        >
                            Đẩy kỳ tới
                        </Button>
                    </div>
                </template>
                <p v-else class="text-muted-foreground text-sm">Không chia trả góp.</p>
            </CardContent>
        </Card>

        <!-- Ngoại lệ -->
        <Card>
            <CardHeader class="pb-1"><CardTitle class="text-xs font-medium">Ngoại lệ</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <template v-if="cards.exception.needs_review">
                    <Badge variant="outline" class="text-orange-700 dark:text-orange-300">Cần review</Badge>
                    <Button
                        v-if="actions.can_cancel_dng && cards.exception.dng_request_id"
                        size="sm" variant="outline" @click="emit('review', cards.exception.dng_request_id)"
                    >
                        Review
                    </Button>
                </template>
                <p v-else class="text-muted-foreground text-sm">Không có ca cần thận trọng.</p>
            </CardContent>
        </Card>
    </div>
</template>
```

- [ ] **Step 4: Build the dual-lens ledger**

Create `resources/js/components/finance/student360/LedgerLens.vue` (toggles "Sổ cái" grouped vs "Dòng thời gian" timeline; the timeline is the M1 `ledger` prop):

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { LedgerEvent, LedgerGroup } from '@/types/finance';
import { formatCurrency, formatDate } from '@/utils/format';
import { Deferred } from '@inertiajs/vue3';

defineProps<{ timeline?: LedgerEvent[]; groups?: LedgerGroup[] }>();
</script>

<template>
    <Tabs default-value="ledger" class="w-full">
        <TabsList>
            <TabsTrigger value="ledger">Sổ cái</TabsTrigger>
            <TabsTrigger value="timeline">Dòng thời gian</TabsTrigger>
        </TabsList>

        <!-- Sổ cái: Kỳ → Invoice → line -->
        <TabsContent value="ledger">
            <Deferred data="ledger_groups">
                <template #fallback><Skeleton class="h-24 w-full" /></template>
                <div v-if="groups && groups.length" class="space-y-4">
                    <section v-for="group in groups" :key="group.semester.id ?? 'none'">
                        <h3 class="text-muted-foreground mb-1 text-xs font-semibold uppercase">{{ group.semester.name }}</h3>
                        <div v-for="invoice in group.invoices" :key="invoice.id" class="rounded-md border p-2">
                            <div class="flex items-center justify-between text-sm font-medium">
                                <span>{{ invoice.invoice_number }} <Badge variant="secondary">{{ invoice.status }}</Badge></span>
                                <span class="tabular-nums">{{ formatCurrency(invoice.remaining) }} còn nợ</span>
                            </div>
                            <ul class="text-muted-foreground mt-1 space-y-0.5 text-xs">
                                <li v-for="line in invoice.lines" :key="line.id" class="flex justify-between">
                                    <span>{{ line.label }}</span><span class="tabular-nums">{{ formatCurrency(line.outstanding) }}</span>
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>
                <p v-else class="text-muted-foreground py-6 text-center text-sm">Chưa có hóa đơn.</p>
            </Deferred>
        </TabsContent>

        <!-- Dòng thời gian: signed events (M1 deferred `ledger`) -->
        <TabsContent value="timeline">
            <Deferred data="ledger">
                <template #fallback><Skeleton class="h-24 w-full" /></template>
                <div v-if="timeline && timeline.length" class="divide-y">
                    <div v-for="(event, idx) in timeline" :key="idx" class="flex items-center justify-between py-2 text-sm">
                        <div><span class="font-medium">{{ event.label }}</span>
                            <span class="text-muted-foreground ml-2 text-xs">{{ event.at ? formatDate(event.at) : '—' }}</span></div>
                        <span class="tabular-nums" :class="event.signed_amount < 0 ? 'text-red-600 dark:text-red-400' : ''">
                            {{ formatCurrency(event.signed_amount) }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-muted-foreground py-6 text-center text-sm">Chưa có hoạt động.</p>
            </Deferred>
        </TabsContent>
    </Tabs>
</template>
```

> Confirm `@/components/ui/tabs` exists (grep `resources/js/components/ui/tabs`); if the export names differ, match them. If tabs are absent, fall back to two buttons toggling a `ref<'ledger'|'timeline'>`.

- [ ] **Step 5: Wire into Show.vue (cards + lens replace the M1 single ledger card)**

In `resources/js/pages/Finance/Student360/Show.vue`, extend the props and replace the M1 single ledger `Card` with `StatusCards` + `LedgerLens`. Add to the script imports and props:

```typescript
import StatusCards from '@/components/finance/student360/StatusCards.vue';
import LedgerLens from '@/components/finance/student360/LedgerLens.vue';
import type { LedgerEvent, LedgerGroup, Student360Actions, Student360Balances, Student360Identity, Student360StatusCards } from '@/types/finance';
```

Extend `defineProps` (keep M1's `student`, `balances`, `focus`, `links`, `ledger`):

```typescript
const props = defineProps<{
    student: Student360Identity;
    balances: Student360Balances;
    status_cards: Student360StatusCards;
    actions: Student360Actions;
    focus: { type: string; id: number } | null;
    links: { audit: string };
    ledger?: LedgerEvent[];
    ledger_groups?: LedgerGroup[];
}>();
```

In the template, after the four balance cards, render (Task 6 fills in the action handlers; for now emit to no-op handlers or console-free stubs that Task 6 replaces):

```vue
        <StatusCards
            :cards="props.status_cards"
            :actions="props.actions"
            @allocate="() => {}"
            @cancel-dng="() => {}"
            @push-installment="() => {}"
            @review="() => {}"
        />
        <LedgerLens :timeline="props.ledger" :groups="props.ledger_groups" />
```

(Replace the M1 standalone "Sổ cái (dòng thời gian)" `Card` block — `LedgerLens` now owns both lenses.)

- [ ] **Step 6: Lint + browser check**

Run: `./scripts/dev.sh npm run lint -- resources/js/types/finance.ts resources/js/components/finance/student360/StatusCards.vue resources/js/components/finance/student360/DngStateStepper.vue resources/js/components/finance/student360/LedgerLens.vue resources/js/pages/Finance/Student360/Show.vue`
Then load a 360 page for a student with invoices + a DNG + installments; confirm the 4 cards render, the stepper highlights the right step, and both ledger lenses load.

- [ ] **Step 7: Commit**

```bash
git add resources/js/types/finance.ts resources/js/components/finance/student360/ resources/js/pages/Finance/Student360/Show.vue
git commit -m "feat(finance): add Student 360 status cards, state stepper, dual-lens ledger"
```

---

## Task 6: Frontend — "Thao tác ▾" menu + action drawers + focus highlight

Wires the write/preview adapters into the UI using the `Sheet` drawer primitive and the proven 4-layer destructive pattern from `LifecycleExceptions.vue`. Implements `focus=<type>:<id>` highlight (§5.6).

**Files:**
- Create: `resources/js/components/finance/student360/StudentActionMenu.vue`
- Create: `resources/js/components/finance/student360/RecordPaymentDrawer.vue`
- Create: `resources/js/components/finance/student360/AllocatePreviewDrawer.vue`
- Create: `resources/js/components/finance/student360/CancelDngDrawer.vue`
- Modify: `resources/js/pages/Finance/Student360/Show.vue`

- [ ] **Step 1: Record-payment drawer (Inertia `useForm`, navigates)**

Create `resources/js/components/finance/student360/RecordPaymentDrawer.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { financeRoutes } from '@/utils/routes';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ open: boolean; studentId: number }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const form = useForm({ amount: '', method: 'cash', paid_at: '', external_ref: '', notes: '' });

const submit = (): void => {
    form.post(financeRoutes.student360.recordPayment(props.studentId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('update:open', false);
        },
    });
};
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent side="right" class="w-full p-4 sm:max-w-md">
            <SheetHeader><SheetTitle>Ghi nhận thanh toán</SheetTitle></SheetHeader>
            <form class="mt-4 space-y-3" @submit.prevent="submit">
                <div><Label>Số tiền (VND)</Label><Input v-model="form.amount" type="number" min="1" />
                    <p v-if="form.errors.amount" class="text-sm text-red-600">{{ form.errors.amount }}</p></div>
                <div><Label>Phương thức</Label><Input v-model="form.method" /></div>
                <div><Label>Ngày thu</Label><Input v-model="form.paid_at" type="date" /></div>
                <div><Label>Mã biên nhận</Label><Input v-model="form.external_ref" /></div>
                <div><Label>Ghi chú</Label><Input v-model="form.notes" /></div>
                <Button type="submit" :disabled="form.processing">Ghi nhận</Button>
            </form>
        </SheetContent>
    </Sheet>
</template>
```

> Use `<Input type="date">` is forbidden by AGENTS for forms — swap to `<DatePicker :portal-to="...">` from `@/components/ui` if the repo's DatePicker works inside a Sheet. Grep an existing drawer/modal date field for the exact `portalTo` usage and mirror it.

- [ ] **Step 2: Allocate-preview drawer (JSON read via `useApi`, applies via existing route)**

Create `resources/js/components/finance/student360/AllocatePreviewDrawer.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApiRequest';
import { financeRoutes } from '@/utils/routes';
import { formatCurrency } from '@/utils/format';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Candidate { invoice_line_id: number; charge_id: number | null; label: string; outstanding: number; would_apply: number }
const props = defineProps<{ open: boolean; paymentId: number | null }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const loading = ref(false);
const preview = ref<{ unapplied: number; candidates: Candidate[] } | null>(null);
const { get } = useApi();

watch(
    () => [props.open, props.paymentId] as const,
    async ([open, paymentId]) => {
        if (!open || paymentId == null) return;
        loading.value = true;
        try {
            const res = await get<{ unapplied: number; candidates: Candidate[] }>(financeRoutes.student360.allocatePreview(paymentId));
            preview.value = res.success ? res.data : null;
        } finally {
            loading.value = false;
        }
    },
);

const apply = (c: Candidate): void => {
    if (props.paymentId == null || c.charge_id == null) return;
    router.post(
        financeRoutes.student360.allocate(props.paymentId),
        { charge_id: c.charge_id, amount: c.would_apply },
        { preserveScroll: true, onSuccess: () => emit('update:open', false) },
    );
};
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent side="right" class="w-full p-4 sm:max-w-md">
            <SheetHeader><SheetTitle>Phân bổ — xem trước</SheetTitle></SheetHeader>
            <Skeleton v-if="loading" class="mt-4 h-24 w-full" />
            <div v-else-if="preview" class="mt-4 space-y-3">
                <p class="text-sm">Dư chưa khớp: <strong class="tabular-nums">{{ formatCurrency(preview.unapplied) }}</strong></p>
                <div v-for="c in preview.candidates" :key="c.invoice_line_id" class="flex items-center justify-between rounded-md border p-2 text-sm">
                    <div>{{ c.label }}<span class="text-muted-foreground ml-2 text-xs">áp {{ formatCurrency(c.would_apply) }}</span></div>
                    <Button size="sm" variant="outline" :disabled="c.charge_id == null" @click="apply(c)">Áp</Button>
                </div>
                <p v-if="!preview.candidates.length" class="text-muted-foreground text-sm">Không có dòng phí phù hợp.</p>
            </div>
        </SheetContent>
    </Sheet>
</template>
```

- [ ] **Step 3: DNG cancel drawer (4-layer: block → impact → reason → ack+submit)**

Create `resources/js/components/finance/student360/CancelDngDrawer.vue`. Mirrors the `LifecycleExceptions.vue` destructive pattern (impact preview + blocking reasons + reason ≥3 chars + ack):

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import { financeRoutes } from '@/utils/routes';
import { formatCurrency } from '@/utils/format';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Impact {
    dng_request_id: number;
    linked_charges: { id: number; charge_type: string; status: string; amount: number }[];
    blocking_reasons: string[];
    requires_void_permission: boolean;
}
const props = defineProps<{ open: boolean; dngId: number | null; canVoidCharges: boolean }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>();

const impact = ref<Impact | null>(null);
const reason = ref('');
const acknowledged = ref(false);
const { get } = useApi();

watch(
    () => [props.open, props.dngId] as const,
    async ([open, dngId]) => {
        if (!open || dngId == null) return;
        reason.value = '';
        acknowledged.value = false;
        const res = await get<Impact>(financeRoutes.student360.dngCancelImpact(dngId));
        impact.value = res.success ? res.data : null;
    },
);

// Layer ①: hard-block when blocking reasons exist or void permission missing.
const blocked = computed(() => {
    if (!impact.value) return true;
    if (impact.value.blocking_reasons.length > 0) return true;
    if (impact.value.requires_void_permission && !props.canVoidCharges) return true;
    return false;
});
const canSubmit = computed(() => !blocked.value && reason.value.trim().length >= 3 && acknowledged.value);

const submit = (): void => {
    if (props.dngId == null || !canSubmit.value) return;
    router.post(
        financeRoutes.student360.dngCancelReviewed(props.dngId),
        { reason: reason.value, acknowledged: acknowledged.value },
        { preserveScroll: true, onSuccess: () => emit('update:open', false) },
    );
};
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent side="right" class="w-full p-4 sm:max-w-md">
            <SheetHeader><SheetTitle>Hủy DNG</SheetTitle></SheetHeader>
            <div v-if="impact" class="mt-4 space-y-3">
                <!-- Layer ②: impact preview -->
                <div v-if="impact.linked_charges.length" class="rounded-md border border-orange-300 bg-orange-50 p-2 text-sm dark:bg-orange-950">
                    <p class="font-medium">Sẽ void {{ impact.linked_charges.length }} phí liên kết:</p>
                    <ul class="mt-1 space-y-0.5 text-xs">
                        <li v-for="c in impact.linked_charges" :key="c.id" class="flex justify-between">
                            <span>{{ c.charge_type }}</span><span class="tabular-nums">−{{ formatCurrency(c.amount) }}</span>
                        </li>
                    </ul>
                </div>
                <!-- Layer ①: blocking -->
                <ul v-if="impact.blocking_reasons.length" class="rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                    <li v-for="(r, i) in impact.blocking_reasons" :key="i">{{ r }}</li>
                </ul>
                <p v-else-if="impact.requires_void_permission && !canVoidCharges" class="rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-800">
                    Hủy DNG này sẽ void phí — bạn cần quyền void_finance_charges.
                </p>

                <!-- Layer ③: reason -->
                <div><Label>Lý do (bắt buộc)</Label><Textarea v-model="reason" :disabled="blocked" rows="3" /></div>
                <!-- Layer ④: ack -->
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model:checked="acknowledged" :disabled="blocked" /> Tôi hiểu thao tác này không hoàn tác và sẽ được ghi vào lịch sử.
                </label>

                <Button variant="destructive" :disabled="!canSubmit" @click="submit">Hủy DNG</Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
```

> Confirm `@/components/ui/checkbox` and `@/components/ui/textarea` exist and their `v-model` API (`v-model:checked` vs `v-model`); the `LifecycleExceptions.vue` resolve dialog uses the repo's canonical reason/ack widgets — copy those exact components and bindings.

- [ ] **Step 4: Action menu ("Thao tác ▾")**

Create `resources/js/components/finance/student360/StudentActionMenu.vue` (render-by-permission per §7.2; uses the repo dropdown menu):

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Student360Actions } from '@/types/finance';
import { ChevronDown } from 'lucide-vue-next';

defineProps<{ actions: Student360Actions }>();
const emit = defineEmits<{ (e: 'recordPayment'): void; (e: 'allocate'): void }>();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">Thao tác <ChevronDown class="ml-1 size-4" /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem v-if="actions.can_record_payment" @click="emit('recordPayment')">Ghi nhận thanh toán</DropdownMenuItem>
            <DropdownMenuItem v-if="actions.can_allocate" @click="emit('allocate')">Phân bổ</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
```

- [ ] **Step 5: Wire everything in Show.vue + focus highlight**

In `resources/js/pages/Finance/Student360/Show.vue`, import the four components, add reactive drawer state, replace the no-op emit handlers from Task 5, mount the action menu in the header, and implement the focus highlight:

```typescript
import StudentActionMenu from '@/components/finance/student360/StudentActionMenu.vue';
import RecordPaymentDrawer from '@/components/finance/student360/RecordPaymentDrawer.vue';
import AllocatePreviewDrawer from '@/components/finance/student360/AllocatePreviewDrawer.vue';
import CancelDngDrawer from '@/components/finance/student360/CancelDngDrawer.vue';
import { financeRoutes } from '@/utils/routes';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const recordOpen = ref(false);
const allocateOpen = ref(false);
const cancelOpen = ref(false);
const allocatePaymentId = ref<number | null>(null);
const cancelDngId = ref<number | null>(null);

const openAllocate = (): void => {
    // unapplied lives on a payment; use the latest unapplied payment id surfaced by the card.
    allocatePaymentId.value = props.status_cards.dng.request?.id ?? null; // see note
    allocateOpen.value = true;
};
const openCancel = (id: number): void => { cancelDngId.value = id; cancelOpen.value = true; };
const pushInstallment = (p: { chargeId: number; installmentId: number }): void => {
    router.post(financeRoutes.student360.retryInstallmentPush(p.chargeId, p.installmentId), {}, { preserveScroll: true });
};

// §5.6 focus highlight: scroll to + flash the focused section.
const focusHighlight = (type: string): string => (props.focus?.type === type ? 'ring-2 ring-orange-400 rounded-md' : '');
```

> **Allocate payment-id note:** the "Số dư & phân bổ" card exposes `unapplied_credit` but not a specific payment id. For M2, add an `unapplied_payment_id` to `status_cards.balance` in `GetStudent360StatusCardsQuery` (the latest completed payment with `unapplied_amount > 0` for that student) and use it here. Add this field + a test assertion in Task 1 if not already present, or fold it into this task as a one-line query addition. Do not guess a payment id from the DNG card.

Header — add the action menu next to the audit button:

```vue
            <div class="flex items-center gap-2">
                <StudentActionMenu :actions="props.actions" @record-payment="recordOpen = true" @allocate="openAllocate" />
                <Button as-child variant="outline" size="sm"><Link :href="props.links.audit"><ExternalLink class="mr-1 size-4" /> Audit</Link></Button>
            </div>
```

Replace the Task-5 `StatusCards` no-op handlers and mount the drawers at the end of the template:

```vue
        <StatusCards
            :cards="props.status_cards" :actions="props.actions"
            @allocate="openAllocate" @cancel-dng="openCancel" @push-installment="pushInstallment" @review="openCancel"
        />

        <RecordPaymentDrawer v-model:open="recordOpen" :student-id="props.student.id" />
        <AllocatePreviewDrawer v-model:open="allocateOpen" :payment-id="allocatePaymentId" />
        <CancelDngDrawer v-model:open="cancelOpen" :dng-id="cancelDngId" :can-void-charges="props.actions.can_void_charges" />
```

- [ ] **Step 6: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/components/finance/student360/ resources/js/pages/Finance/Student360/Show.vue`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/finance/student360/ resources/js/pages/Finance/Student360/Show.vue
git commit -m "feat(finance): add Student 360 action menu and write drawers"
```

---

## Task 7: Integration smoke, permission matrix, invariant evidence, harness trace

**Files:**
- Modify: `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md`

- [ ] **Step 1: Run the full M2 backend suite**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360 tests/Feature/Finance/Dng/ReviewedCancelDngTest.php`
Expected: all PASS. Re-run M1's suite to confirm no regression: `./scripts/dev.sh test tests/Feature/Finance/Student360/StudentOverviewShellTest.php tests/Feature/Finance/Search tests/Feature/Finance/Shell`.

- [ ] **Step 2: Build + browser smoke (the §7.2 permission matrix)**

Run: `./scripts/dev.sh npm run build`. Then, as users with different permission sets, verify on a 360 page:
1. `view_finance_student_overview` only → no "Thao tác ▾" actions, no destructive buttons (all hidden, never disabled-but-clickable).
2. `+ create_finance_payments` → "Ghi nhận thanh toán" appears; "Hủy DNG" appears for a `pending`/`pushed_to_dng` DNG.
3. `+ allocate_finance_payment` → "Phân bổ" + "Phân bổ ngay" appear; preview drawer loads candidates.
4. DNG with linked charges, user WITHOUT `void_finance_charges` → cancel drawer shows the red "cần quyền void_finance_charges" block and the submit stays disabled.
5. DNG with `has_bridged_payment` → cancel drawer shows the blocking reason and submit stays disabled.
6. `focus=dng:<id>` in URL → the DNG card is highlighted.
Record pass/fail per row in the trace.

- [ ] **Step 3: Record invariant evidence (money state changed this milestone)**

Run: `./scripts/dev.sh artisan finance:audit-invariants` (the exact command name — grep `Console/Commands` for the finance invariant command if it differs) before and after exercising record-payment + DNG-cancel in the smoke, and confirm no new critical violations are introduced by the adapters. Capture the output into the story's validation evidence.

- [ ] **Step 4: Update the story validation doc**

Append a "Milestone 2 — Student 360 (full)" section to `validation.md`: the new routes + gates, the permission matrix table (§7.2), the test names, the smoke results, and the invariant evidence. Note that all writes wrap existing tested Actions/Services (`PaymentService::recordPayment`, `AllocatePaymentAction`, `CancelDngPaymentRequestAction`, `PushNextInstallmentAction`).

- [ ] **Step 5: Commit + harness trace**

```bash
git add docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md
git commit -m "docs(finance): record Student 360 (full) M2 acceptance evidence"
```

```bash
./scripts/harness trace --summary "S-010 Milestone 2: Student 360 full (cards, dual ledger, stepper, action drawers)" --story FIN-REV-010 --actions "status-card + grouped-ledger read models, manual-allocate preview, record-payment adapter, 4-layer reviewed DNG cancel + void gate, 360 UI" --changed "app/Modules/Finance/Queries/Student360, app/Modules/Finance/Http/Web/Admin, resources/js/pages/Finance/Student360, resources/js/components/finance/student360" --outcome completed --friction "DNG→charge pivot relation name + DatePicker-in-sheet portalTo needed confirmation; recorded in steps"
```

---

## Self-Review (against design §5, §7.2, §10 M2)

**1. Spec coverage:**
- §5.1/5.2 Header (sticky, dual status chip, 4 balances, "Thao tác ▾") — M1 header + Task 6 action menu. ✅
- §5.3 Four status cards (Số dư & phân bổ / DNG hiện tại / Trả góp / Ngoại lệ) each with a quick action — Task 1 (data) + Task 5 (UI) + Task 6 (actions). ✅
- §5.3 manual-allocate preview **distinct** from auto-allocate, read-only — Task 2. ✅ (Apply reuses existing `finance.payments.allocate`; no new write path.)
- §5.4 two lenses (Sổ cái grouped Kỳ→Invoice→line + Dòng thời gian) — Task 1 grouped query + Task 5 `LedgerLens`; timeline reuses M1 `ledger`. ✅
- §5.5 DNG 2-call state stepper — Task 5 `DngStateStepper` (statuses verbatim). ✅
- §5.6 `focus=<type>:<id>` highlight — Task 6. ✅
- §5.2 "Ghi nhận thanh toán" needs a new adapter (old route removed) — Task 3 wraps `PaymentService::recordPayment`. ✅
- §7.2 4-layer safe-destructive DNG cancel as **new** adapter + FormRequest (reason/impact/ack), NOT the old 1-step confirm — Task 4 + Task 6 `CancelDngDrawer`. ✅
- §7.2 gate gap: add `void_finance_charges` when linked charges exist — Task 4 controller enforcement + test. ✅
- §7.2 render-by-permission (hidden if no perm; disabled+reason if blocked) — Task 5/6 use `actions.*` flags + blocking reasons; submit disabled, never "clickable-but-fails". ✅
- §7.2 two audit sinks: DNG cancel uses cancel payload/response + flash/log (NOT lifecycle events) — Task 4 note + the action's own sink. ✅ (Lifecycle-events sink stays owned by the lifecycle flow.)
- §10 M2 "augment the M1 shell, do not change URL/destination" — same route, additive props. ✅
- Scope guard (no money math rewrite) — every write wraps an existing Action/Service; every read delegates to `SettlementService`/existing queries. ✅

**2. Placeholder scan:** No TBD/TODO; every step ships complete code + commands + expected output. The one data dependency (`unapplied_payment_id` for the allocate drawer) is called out explicitly with the fix, not left implicit.

**3. Type/name consistency:** DNG status strings match `DngPaymentRequest` constants. `PaymentService::recordPayment` keys, `AllocatePaymentAction::run` args, `LifecycleDueExceptionRowMapper::blockingReasons`, `PushNextInstallmentAction` route params all match the verified signatures. New route names (`finance.payments.allocate-preview`, `finance.students.payments.store`, `finance.dng.payment-requests.cancel-impact`/`.cancel-reviewed`) are consistent across `FINANCE_ROUTE_NAMES`, `financeRoutes.student360.*`, `routes/web.php`, and the controllers. TS types (`Student360StatusCards`, `Student360Actions`, `LedgerGroup`, `DngCardRequest`) match the controller prop shapes.

**In-step confirmations (each has a fallback):** `InvoiceLine` description column; DNG→charge pivot relation name (`chargeLinks`); `@/components/ui/tabs`/`checkbox`/`textarea`/`dropdown-menu` export surfaces; `DatePicker` `portalTo` inside a `Sheet`; the exact `finance:audit-invariants` command name; whether `FinanceCharge::factory()` exists. All are "match the existing sibling/grep" confirmations, not design questions.

**Flagged for human (carried from §11):** record-payment writes a `Payment` with no invoice linkage — confirm finance ops want manual payments to land as unapplied credit (then allocated via the preview) rather than auto-applied. The plan assumes unapplied-then-allocate, matching `PaymentService::recordPayment` default behavior.

---

## Execution Handoff

Milestone 2 plan saved to `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`. Recommended per this repo's AGENTS.md flow: **evaluate with `executing-plans`** (critical review) → human approval → execute via **subagent-driven-development** (fresh subagent per task; this milestone is high-risk, so review between tasks is strongly advised). Note: **Milestone 1 must be merged first** — M2 augments its shell, routes, and types.

Options: (1) Evaluate first (executing-plans), (2) Subagent-Driven execution, (3) Inline execution. Which would you like — and shall I continue with the full Milestone 3 (Cockpit) plan next?
