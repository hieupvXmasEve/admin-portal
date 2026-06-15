# Finance Office — Lookup & Audit — Implementation Plan (Milestone 5)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the "🔎 Tra cứu & Audit" group — turn the three demoted object-lists (**Charge Ledger / Invoices / Payments**) into one consistent, dense, server-driven **lookup standard** (sort + filter + paginate, sticky header, right-aligned tabular money, status chips, **row → Student 360**, **multi-select → Batch Studio**), and make the existing **Audit Workspace** a proper drilldown **destination** (money-flow graph + signed-ledger timeline + deep-link landing). Read-only: **no money writes, no money math** — reuse `SettlementService`, the existing list queries, and the audit graph/timeline builders.

**Architecture:** Thin controllers → extracted read-only Query classes → `useDataTable` Inertia pages. Each lookup surface gets a `Filter*Request` (centralized, documented filter contract) and a `List*Query` (campus-scoped, whitelisted sort, server pagination); the three Vue pages converge on the same composable (`useDataTable`) + Table primitives + `DataPagination`, a shared row-action cell (row→360 with a `focus` param), and a shared selection composable that hands the selected student IDs to a Batch Studio job (M4). The Audit Workspace keeps its existing `GetFinanceAuditGraphQuery` / `FinanceLedgerTimelineBuilder` / `FinanceAuditWarningBuilder` props and adds a real node/edge money-flow panel (replacing today's count-only summary) plus deep-link acceptance. The §4.4 invariant-drilldown **params** (`finding_code`/`scope`/`sample_id` + `FinanceInvariantSampleResolver`) are **owned by M3** (design §10) and are **consumed, not re-implemented, here**.

**Tech Stack:** Laravel 13, Inertia v3 (`useDataTable`, `Inertia::render`, `Inertia::defer`), Vue 3 `<script setup lang="ts">`, Tailwind v4, Ziggy, Pest, `@/components/ui/table/*` primitives, `DataPagination.vue`, `lucide-vue-next`, existing `formatCurrency`/badge maps in `resources/js/types/finance.ts` + `resources/js/utils/format.ts`.

**Depends on:** M1 (`financeRoutes` + `FINANCE_ROUTE_NAMES`, shared `semester` prop, `useDataTable` foundation), M2 (Student 360 `focus` deep-link param + `LedgerLens` patterns), M3 (invariant-drilldown params — **consumed**; if M3 is unmerged the `finding_code` landing is simply absent and the Audit page still works for `target_type` drilldowns), M4 (Batch Studio — the multi-select hand-off targets `financeRoutes.batchStudio.dng()` / `.reminders()`, which read `student_ids`). **Merge-order note:** the batch hand-off (Task 9 wiring inside Tasks 6–8) requires M4 merged; the invariant-finding deep link (Task 9 of M3) requires M3 merged. Neither blocks the lookup-table work itself.

**Story:** `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/` (`FIN-REV-010`). Design source: `docs/features/finance/finance-office-ux-redesign-design.md` §3.2 (sidebar IA), §4.4 (invariant drilldown destination), §5.4/§7.1 (signed ledger), §7.3 (dense data table standard), §7.4 (semantic colors), §10 milestone 5. **Portal impact: none.**

**Risk lane:** **low-to-normal** (`read-model`, `authz`, campus-scope correctness). **No money writes and no money math** are added — every amount comes from existing queries / `SettlementService`. Hard gates:
- **Preserve per-surface permissions** exactly: `view_finance_charges` (charges), `view_finance_invoices` (invoices), `view_finance_payments` (payments), `view_finance_audit_workspace` (audit). No new permission is required for lookup.
- **Campus scope unchanged:** "global" = current campus unless `view_finance_all_campus` (badge must say which). The extracted queries must keep the existing campus filter.
- **Row → 360 / batch hand-off respect permissions:** a row action that the operator can't perform must not appear (e.g. no "Send to Batch Studio · Đẩy DNG" without `create_finance_payments`).
- **Detail/show pages untouched:** the legacy `finance.charges.show` / `finance.invoices.show` / `finance.payments.show` pages stay (Audit `SOURCE_ROUTES` deep-links into them); lookup row-click changes to 360 but the detail pages remain reachable.

---

## Testing approach (same as M1–M4 — read first)

Backend = TDD with Pest (`./scripts/dev.sh test <path>`), using the proven finance pattern: a `grantFinance(User, string[] $perms, Campus)` helper (Task 0) that mocks `PermissionService::getUserPermissions`, sets `session(['current_campus_id' => …])` + `app()->singleton('campus', …)`, then `assertInertia` / data assertions. The new Query classes are pure read models → unit/feature tests assert filter + whitelisted-sort + campus-scope behavior directly.

Frontend = `./scripts/dev.sh npm run lint -- <files>` + browser smoke (Task 10). **No JS unit runner**; do **not** run whole-project `vue-tsc` (OOMs in the dev container) — lint edited files only.

**Read-only milestone:** M5 performs no money writes, so there is no invariant-gate requirement. Task 10 still runs `finance:audit-invariants` once as a **sanity check** (numbers must be unchanged by a read-only milestone).

---

## File Structure (M5)

**Created — backend read models + filter requests:**
- `app/Modules/Finance/Queries/Lookup/ListFinanceChargesQuery.php` — extracts the inline charge-list query; adds whitelisted sort.
- `app/Modules/Finance/Queries/Lookup/ListStudentInvoicesQuery.php` — wraps the invoice model scopes; adds whitelisted sort.
- `app/Modules/Finance/Http/Requests/Lookup/FilterFinanceChargesRequest.php`
- `app/Modules/Finance/Http/Requests/Lookup/FilterStudentInvoicesRequest.php`
- `app/Modules/Finance/Http/Requests/Lookup/FilterPaymentsRequest.php`

**Modified — backend:**
- `app/Modules/Finance/Http/Web/Admin/FinanceChargeController.php` — `index` becomes thin (delegates to the query + request).
- `app/Modules/Finance/Http/Web/Admin/BillingInvoiceController.php` — `index` becomes thin.
- `app/Modules/Finance/Http/Web/Admin/PaymentController.php` — `index` uses `FilterPaymentsRequest` (validation only; `ListPaymentsQuery` already supports `date_range` + sort).
- `app/Modules/Finance/Queries/ListPaymentsQuery.php` — no behavior change; confirm the sortable whitelist + `date_range` (already present).

**Created — frontend shared:**
- `resources/js/composables/useLookupSelection.ts` — row-selection set + "send to Batch Studio" navigation (carries student IDs).
- `resources/js/components/finance/lookup/SendToBatchBar.vue` — the sticky multi-select action bar.
- `resources/js/components/finance/lookup/LookupRowActions.vue` — row→360 (`focus`) + "open detail (audit)" secondary link.
- `resources/js/components/finance/audit/MoneyFlowGraph.vue` — node/edge money-flow panel for the Audit Workspace.

**Modified — frontend:**
- `resources/js/pages/Finance/Charges/Index.vue` — migrate to `useDataTable` + sortable heads + row→360 + selection.
- `resources/js/pages/Finance/Invoices/Index.vue` — migrate to `useDataTable` + sortable heads + row→360 + selection (keep Excel export).
- `resources/js/pages/Finance/Payments/Index.vue` — migrate `useInertiaFilters` → `useDataTable` + date-range UI + row→360 + selection.
- `resources/js/pages/Finance/Audit/Workspace.vue` — add `MoneyFlowGraph` + signed-timeline coloring + deep-link landing.
- `resources/js/types/finance.ts` — lookup row/selection types.

**Tests:**
- `tests/Feature/Finance/Lookup/ChargeLookupTest.php`
- `tests/Feature/Finance/Lookup/InvoiceLookupTest.php`
- `tests/Feature/Finance/Lookup/PaymentLookupTest.php`
- `tests/Feature/Finance/Lookup/LookupAuthzTest.php`

---

## Verified backend facts this plan builds on

Confirmed by reading the code (`path:line`). M5 **reuses** these; it changes presentation + extracts read models, never the money logic.

**Charge Ledger (global) — `finance.charges.index`, `can:view_finance_charges`** (`routes/web.php`):
- `FinanceChargeController::index(Request): Response` (`Http/Web/Admin/FinanceChargeController.php:49–136`) — **inline** query: validates `search,student_id,semester_id,charge_type,status,per_page`; `FinanceCharge::query()->with(['student','semester','createdBy'])`; filters search (description + student name/code/email), `semester_id`, `charge_type`, `status`; `orderByDesc('effective_at')`; `paginate(20)`. Renders `Finance/Charges/Index` with `charges, semesters, chargeTypes, filters, student?`.
- Vue `resources/js/pages/Finance/Charges/Index.vue` — **manual** filter state (debounced search), eye-icon → `finance.charges.show`, **no column sort, no row-click, no selection**.

**Invoices — `finance.invoices.index`, `can:view_finance_invoices`**:
- `BillingInvoiceController::index(Request)` (`Http/Web/Admin/BillingInvoiceController.php:26–67`) — `StudentInvoice::query()->with(['student','semester'])`, scopes `forCampus(app('campus')?->id)`, `forSemester`, `search`, `filterByStatus`; `->latest()`; `paginate(50)->withQueryString()`; appends `real_time_status,total_amount,paid_amount,outstanding_balance`. Renders `Finance/Invoices/Index` with `invoices, filters`. Excel export route `finance.invoices.export`.
- Vue `resources/js/pages/Finance/Invoices/Index.vue` — `useTableFilters` (older wrapper), eye-icon → `finance.invoices.show`, **no sort, no row-click, no selection**.

**Payments — `finance.payments.index`, `can:view_finance_payments`**:
- `PaymentController::index(Request, ListPaymentsQuery): Response` (`Http/Web/Admin/PaymentController.php:30–39`) — delegates to the query.
- `ListPaymentsQuery::handle(Request): array{items, stats}` (`Queries/ListPaymentsQuery.php`) — `Payment::query()->with(['student','receivedBy'])->withSum('applications as applied_amount_total')`; filters `search`, `source`, `status`, **`date_range` → `whereBetween('paid_at', …)` (`:88–94`)**; sortable whitelist `amount,paid_at,status,created_at,student_id,student_name,unapplied_amount` (`:118–143`); default `paid_at DESC`. Rows: `{id,amount,paid_at,source,external_ref,status,method,allocated_amount,unapplied_amount, student:{id,full_name,student_id}}`; stats `{payment_count,total_paid,total_applied,total_unapplied}`.
- Vue `resources/js/pages/Finance/Payments/Index.vue` — `useInertiaFilters` (older), **row click → `finance.payments.show`** (not 360), sortable heads, **date_range UI not implemented** though the query supports it.

**Lookup standard (reuse exactly):**
- `useDataTable` (`resources/js/composables/useDataTable.ts`) — the **current** standard, used by `Finance/Operations/DngWorklist.vue`, `Settlement.vue`, `LifecycleExceptions.vue`. Options: `{ baseUrl, initialFilters, defaultValues, only, debounce, fieldDebounce, immediateFields, preserveScroll, replace }`. Returns `{ filters, setFilter, clearFilter, clearAllFilters, setSort, clearSort, setPage, setPerPage, apply, refresh, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, hasActiveFilters, isLoading, currentSort, currentDirection }`. **Mirror an existing finance consumer** (`Finance/Operations/Settlement.vue`) for exact markup.
- `DataPagination.vue` (`resources/js/components/DataPagination.vue`) — prop `pagination: { from,to,total,current_page,last_page,prev_page_url,next_page_url,links,per_page }`, `pageSizeOptions` (default `[10,25,50,100]`); emits `navigate(url)`, `pageSizeChange(size)`.
- Table primitives `@/components/ui/table/*` (`TableHeader,TableBody,TableRow,TableHead,TableCell,TableEmpty`). **No sticky-header primitive** → apply `class="sticky top-0 z-10 bg-background"` on `TableHead` per the §7.3 standard.
- `resources/js/types/finance.ts` — `formatCurrency`, `CHARGE_TYPE_LABELS/BADGE_CLASSES`, `CHARGE_STATUS_*`, `PAYMENT_STATUS_*` + `getChargeStatusBadgeClass()` etc. `resources/js/utils/format.ts` — `formatDate`, `formatDateTime`. §7.4 colors already encoded in the badge maps.
- `financeRoutes.lookup.chargeLedger()` / `.invoices()`, `financeRoutes.collect.payments()`, `financeRoutes.audit()`, `financeRoutes.students.overview(studentId, focus?)` (`resources/js/utils/routes.ts`). Shared `semester` prop `{ selected_id, options[] }` (`HandleInertiaRequests`).
- Batch Studio (M4): `financeRoutes.batchStudio.dng()` / `.reminders()`; `DngPush.vue` `defaultSetup.student_ids` reads pre-selected IDs.

**Audit Workspace — `finance.audit.index`, `can:view_finance_audit_workspace`** (already shipped, S-009):
- `FinanceAuditWorkspaceController::index(FinanceAuditSearchRequest, ResolveFinanceAuditSearchQuery, GetFinanceAuditGraphQuery, FinanceLedgerTimelineBuilder, FinanceAuditWarningBuilder)` (`:41–100`) → renders `Finance/Audit/Workspace` with `filters, resolution, links.source, allowed_actions.export, graph(deferred), timeline(deferred), warnings(deferred)`. `SOURCE_ROUTES` deep-links per target type.
- `FinanceAuditSearchRequest::rules()` (`:19–28`) — `q, target_type(in:student,invoice,payment,charge,dng), target_id, semester_id, billing_cycle_id`. ⚠️ **`finding_code` / `scope` / `sample_id` and `FinanceInvariantSampleResolver` are MISSING** (proposed by the M3 Cockpit plan, not yet merged). M5 does **not** add them.
- `GetFinanceAuditGraphQuery::handle(array $target, ?int $semesterId, ?int $billingCycleId): array{subject, student_id, nodes[], edges[], derived_balance[], ledger_entries[]}`. `nodes[]`: `{key:"type:id", type, id, label, status?, amount?, date?}`; `edges[]`: `{from:"type:id", to:"type:id", kind, amount?}`; `derived_balance[]`: `{invoice_id, invoice_number, cached_total_amount, cached_paid_amount, derived_net, derived_paid, drift:bool}`.
- `FinanceLedgerTimelineBuilder::build(array $graph): list<{at?, type, signed_amount, label, refs}>` (signed; reversals stay negative).
- Vue `resources/js/pages/Finance/Audit/Workspace.vue` — renders the graph as a **count-only summary** (node-group badges + edge count), the derived_balance table, the signed timeline, and the warnings panel. **No real node/edge view** — that is M5's audit deliverable (design §10 "audit graph đầy đủ (mục 5)").

---

## Task 0: Shared `grantFinance()` test helper (Lookup suite)

Mirror the M1–M4 auth bootstrap. If M4's `tests/Feature/Finance/Batch/helpers.php` already defines `grantFinance`, **move it** to the shared path below and require it once to avoid a redeclare clash.

**Files:**
- Create: `tests/Feature/Finance/Lookup/helpers.php`
- Modify: `tests/Pest.php`

- [ ] **Step 1: Write the helper**

Create `tests/Feature/Finance/Lookup/helpers.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Services\PermissionService;

if (! function_exists('grantFinance')) {
    /**
     * @param  string[]  $permissions
     */
    function grantFinance(User $user, array $permissions, Campus $campus): void
    {
        session(['current_campus_id' => $campus->id]);
        app()->singleton('campus', fn () => $campus);

        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($permissions);
        app()->instance(PermissionService::class, $mock);
    }
}
```

> The `function_exists` guard means this file and an M4 copy can coexist during a transition; once both milestones land, keep a single shared definition.

- [ ] **Step 2: Autoload it** — in `tests/Pest.php`, add:

```php
require_once __DIR__.'/Feature/Finance/Lookup/helpers.php';
```

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Finance/Lookup/helpers.php tests/Pest.php
git commit -m "test(finance): add grantFinance helper for Lookup suite"
```

---

## Task 1: `ListFinanceChargesQuery` + `FilterFinanceChargesRequest` (extract + sort)

Extract the inline charge-list query into a testable read model, keep the existing filters + campus scope, and add a **whitelisted** server sort (the page currently can't sort). No money math changes.

**Files:**
- Create: `app/Modules/Finance/Http/Requests/Lookup/FilterFinanceChargesRequest.php`
- Create: `app/Modules/Finance/Queries/Lookup/ListFinanceChargesQuery.php`
- Test: `tests/Feature/Finance/Lookup/ChargeLookupTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Lookup/ChargeLookupTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Queries\Lookup\ListFinanceChargesQuery;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
    grantFinance($this->user, ['view_finance_charges'], $this->campus);
});

it('filters charges by status and sorts by a whitelisted column', function () {
    $student = Student::factory()->create(['campus_id' => $this->campus->id]);
    FinanceCharge::factory()->create(['student_id' => $student->id, 'semester_id' => $this->semester->id, 'status' => 'active', 'amount' => 100, 'charge_type' => 'manual_fee']);
    FinanceCharge::factory()->create(['student_id' => $student->id, 'semester_id' => $this->semester->id, 'status' => 'active', 'amount' => 300, 'charge_type' => 'manual_fee']);
    FinanceCharge::factory()->create(['student_id' => $student->id, 'semester_id' => $this->semester->id, 'status' => 'void', 'amount' => 200, 'charge_type' => 'manual_fee']);

    $request = Request::create('', 'GET', ['status' => 'active', 'sort' => 'amount', 'direction' => 'asc']);
    $result = app(ListFinanceChargesQuery::class)->handle($request);

    $amounts = collect($result['items']->items())->map(fn ($c) => (float) $c->amount)->all();
    expect($amounts)->toBe([100.0, 300.0]); // void excluded, ascending
});

it('ignores a non-whitelisted sort column and falls back to effective_at desc', function () {
    $student = Student::factory()->create(['campus_id' => $this->campus->id]);
    FinanceCharge::factory()->create(['student_id' => $student->id, 'semester_id' => $this->semester->id, 'status' => 'active']);

    $request = Request::create('', 'GET', ['sort' => 'amount); DROP TABLE', 'direction' => 'asc']);
    $result = app(ListFinanceChargesQuery::class)->handle($request);

    expect($result['items']->total())->toBe(1); // no SQL error, safe fallback
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Lookup/ChargeLookupTest.php`
Expected: FAIL — `ListFinanceChargesQuery` not found.

- [ ] **Step 3: Write the FormRequest**

Create `app/Modules/Finance/Http/Requests/Lookup/FilterFinanceChargesRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

class FilterFinanceChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_charges');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'student_id' => 'nullable|integer',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'charge_type' => 'nullable|string',
            'status' => 'nullable|string|in:active,void,transferred',
            'sort' => 'nullable|string',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
```

- [ ] **Step 4: Write the query**

Create `app/Modules/Finance/Queries/Lookup/ListFinanceChargesQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Lookup;

use App\Models\FinanceCharge;
use Illuminate\Http\Request;

final class ListFinanceChargesQuery
{
    /** Columns the client may sort by (anything else falls back to the default). */
    private const SORTABLE = ['amount', 'effective_at', 'status', 'charge_type', 'created_at'];

    /**
     * @return array{items: \Illuminate\Pagination\LengthAwarePaginator}
     */
    public function handle(Request $request): array
    {
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $query = FinanceCharge::query()->with(['student', 'semester', 'createdBy']);

        if ($campusId !== null) {
            $query->whereHas('student', fn ($q) => $q->where('campus_id', $campusId));
        }

        if ($request->filled('search')) {
            $term = (string) $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', "%{$term}%")
                    ->orWhereHas('student', fn ($s) => $s->where('full_name', 'like', "%{$term}%")
                        ->orWhere('student_id', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
            });
        }

        $query->when($request->filled('student_id'), fn ($q) => $q->where('student_id', (int) $request->input('student_id')));
        $query->when($request->filled('semester_id'), fn ($q) => $q->where('semester_id', (int) $request->input('semester_id')));
        $query->when($request->filled('charge_type'), fn ($q) => $q->where('charge_type', $request->input('charge_type')));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        $sort = (string) $request->input('sort', '');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, self::SORTABLE, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByDesc('effective_at');
        }

        $perPage = (int) $request->input('per_page', 20);

        return ['items' => $query->paginate($perPage)->withQueryString()];
    }
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Lookup/ChargeLookupTest.php`
Expected: PASS (2 passed). If `FinanceCharge::factory()` is missing, create a minimal factory or insert rows directly in the test; keep assertions on ordering/exclusion.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Finance/Http/Requests/Lookup/FilterFinanceChargesRequest.php app/Modules/Finance/Queries/Lookup/ListFinanceChargesQuery.php tests/Feature/Finance/Lookup/ChargeLookupTest.php
git commit -m "feat(finance): extract ListFinanceChargesQuery + filter request with whitelisted sort"
```

---

## Task 2: Thin `FinanceChargeController::index`

Make the controller delegate to the request + query (DRY, testable) while keeping the exact Inertia component + the filter-option props (`semesters`, `chargeTypes`).

**Files:**
- Modify: `app/Modules/Finance/Http/Web/Admin/FinanceChargeController.php`
- Test: `tests/Feature/Finance/Lookup/ChargeLookupTest.php` (add an HTTP/Inertia assertion)

- [ ] **Step 1: Add the failing Inertia test** — append to `ChargeLookupTest.php`:

```php
it('renders the charge ledger with sorted items via the controller', function () {
    $student = Student::factory()->create(['campus_id' => $this->campus->id]);
    FinanceCharge::factory()->count(2)->create(['student_id' => $student->id, 'semester_id' => $this->semester->id, 'status' => 'active', 'charge_type' => 'manual_fee']);

    $this->actingAs($this->user)
        ->get(route('finance.charges.index', ['sort' => 'amount', 'direction' => 'asc']))
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Finance/Charges/Index')
            ->has('charges.data', 2)
            ->where('filters.sort', 'amount')
        );
});

it('forbids the charge ledger without view_finance_charges', function () {
    grantFinance($this->user, ['view_finance_invoices'], $this->campus);
    $this->actingAs($this->user)->get(route('finance.charges.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Lookup/ChargeLookupTest.php`
Expected: FAIL — `filters.sort` missing (controller doesn't pass sort yet) and/or authz not enforced via the new request.

- [ ] **Step 3: Rewrite `index`** — replace the inline body of `FinanceChargeController::index` (`:49–136`) with:

```php
public function index(
    \App\Modules\Finance\Http\Requests\Lookup\FilterFinanceChargesRequest $request,
    \App\Modules\Finance\Queries\Lookup\ListFinanceChargesQuery $query,
): \Inertia\Response {
    $result = $query->handle($request);

    return \Inertia\Inertia::render('Finance/Charges/Index', [
        'charges' => $result['items'],
        'semesters' => \App\Models\Semester::query()->where('is_archived', false)->orderByDesc('start_date')->get(['id', 'code', 'name']),
        'chargeTypes' => \App\Modules\Finance\Enums\ChargeTypeOptions::all() ?? [], // match the existing options source used today
        'filters' => $request->only(['search', 'student_id', 'semester_id', 'charge_type', 'status', 'sort', 'direction', 'per_page']),
        'student' => $request->filled('student_id')
            ? \App\Models\Student::query()->find((int) $request->input('student_id'), ['id', 'full_name', 'student_id'])
            : null,
    ]);
}
```

> **Verify before writing:** keep the *same* `chargeTypes` source the current controller uses (the inline version built it from an enum/constant — reuse that exact call, don't invent `ChargeTypeOptions::all()` if a different accessor exists). Keep the same `semesters` column set the page expects.

- [ ] **Step 4: Run to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Lookup/ChargeLookupTest.php`
Expected: PASS (4 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Http/Web/Admin/FinanceChargeController.php tests/Feature/Finance/Lookup/ChargeLookupTest.php
git commit -m "refactor(finance): thin FinanceChargeController::index via query + filter request"
```

---

## Task 3: `ListStudentInvoicesQuery` + `FilterStudentInvoicesRequest` + thin invoice controller

Wrap the invoice model scopes in a read model, add whitelisted sort, and thin the controller — keeping the appended computed fields (`real_time_status`, `total_amount`, `paid_amount`, `outstanding_balance`) and the Excel export route.

**Files:**
- Create: `app/Modules/Finance/Http/Requests/Lookup/FilterStudentInvoicesRequest.php`
- Create: `app/Modules/Finance/Queries/Lookup/ListStudentInvoicesQuery.php`
- Modify: `app/Modules/Finance/Http/Web/Admin/BillingInvoiceController.php`
- Test: `tests/Feature/Finance/Lookup/InvoiceLookupTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Lookup/InvoiceLookupTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
    grantFinance($this->user, ['view_finance_invoices'], $this->campus);
});

it('renders campus-scoped invoices with the filters echoed back', function () {
    $student = Student::factory()->create(['campus_id' => $this->campus->id]);
    StudentInvoice::factory()->count(2)->create(['student_id' => $student->id, 'semester_id' => $this->semester->id]);

    $this->actingAs($this->user)
        ->get(route('finance.invoices.index', ['sort' => 'due_date', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Invoices/Index')
            ->has('invoices.data')
            ->where('filters.sort', 'due_date')
        );
});

it('forbids the invoice lookup without view_finance_invoices', function () {
    grantFinance($this->user, ['view_finance_charges'], $this->campus);
    $this->actingAs($this->user)->get(route('finance.invoices.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Lookup/InvoiceLookupTest.php`
Expected: FAIL — `filters.sort` absent / request not wired.

- [ ] **Step 3: Write the FormRequest**

Create `app/Modules/Finance/Http/Requests/Lookup/FilterStudentInvoicesRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

class FilterStudentInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_invoices');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'status' => 'nullable|string',
            'sort' => 'nullable|string',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
```

- [ ] **Step 4: Write the query** — reuse the existing model scopes; add a whitelisted sort over real DB columns (computed fields like `outstanding_balance` are appended, not columns, so they are **not** sortable server-side — document that):

Create `app/Modules/Finance/Queries/Lookup/ListStudentInvoicesQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Lookup;

use App\Models\StudentInvoice;
use Illuminate\Http\Request;

final class ListStudentInvoicesQuery
{
    /** Real columns only — appended computed totals are not sortable in SQL. */
    private const SORTABLE = ['invoice_number', 'due_date', 'created_at', 'cached_total_amount', 'cached_paid_amount'];

    /**
     * @return array{items: \Illuminate\Pagination\LengthAwarePaginator}
     */
    public function handle(Request $request): array
    {
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $query = StudentInvoice::query()->with(['student', 'semester']);

        if ($campusId !== null) {
            $query->forCampus($campusId);
        }

        $query->when($request->filled('semester_id'), fn ($q) => $q->forSemester((int) $request->input('semester_id')));
        $query->when($request->filled('search'), fn ($q) => $q->search((string) $request->input('search')));
        $query->when($request->filled('status'), fn ($q) => $q->filterByStatus((string) $request->input('status')));

        $sort = (string) $request->input('sort', '');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, self::SORTABLE, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest();
        }

        $invoices = $query->paginate((int) $request->input('per_page', 50))->withQueryString();
        $invoices->getCollection()->each->append(['real_time_status', 'total_amount', 'paid_amount', 'outstanding_balance']);

        return ['items' => $invoices];
    }
}
```

> **Verify the scope names** (`forCampus`, `forSemester`, `search`, `filterByStatus`) and the appended accessor names against `StudentInvoice` before writing — the second research pass confirmed these exact scopes/appends, but match the model.

- [ ] **Step 5: Thin the controller** — replace `BillingInvoiceController::index` (`:26–67`) with:

```php
public function index(
    \App\Modules\Finance\Http\Requests\Lookup\FilterStudentInvoicesRequest $request,
    \App\Modules\Finance\Queries\Lookup\ListStudentInvoicesQuery $query,
) {
    return \Inertia\Inertia::render('Finance/Invoices/Index', [
        'invoices' => $query->handle($request)['items'],
        'filters' => $request->only(['search', 'semester_id', 'status', 'sort', 'direction', 'per_page']),
    ]);
}
```

- [ ] **Step 6: Run to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Lookup/InvoiceLookupTest.php`
Expected: PASS (2 passed).

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Finance/Http/Requests/Lookup/FilterStudentInvoicesRequest.php app/Modules/Finance/Queries/Lookup/ListStudentInvoicesQuery.php app/Modules/Finance/Http/Web/Admin/BillingInvoiceController.php tests/Feature/Finance/Lookup/InvoiceLookupTest.php
git commit -m "refactor(finance): extract ListStudentInvoicesQuery + thin invoice controller with sort"
```

---

## Task 4: `FilterPaymentsRequest` (validate filters; `ListPaymentsQuery` already supports date-range + sort)

Payments already has a proper query (`ListPaymentsQuery`) with `date_range` + a sortable whitelist. M5 only adds a `FormRequest` to centralize/validate the filter contract (currently inline) and wires it into the controller — no query change.

**Files:**
- Create: `app/Modules/Finance/Http/Requests/Lookup/FilterPaymentsRequest.php`
- Modify: `app/Modules/Finance/Http/Web/Admin/PaymentController.php` (`index` accepts the request)
- Test: `tests/Feature/Finance/Lookup/PaymentLookupTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Lookup/PaymentLookupTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->user = User::factory()->create();
    grantFinance($this->user, ['view_finance_payments'], $this->campus);
});

it('renders payments with stats and accepts a date_range filter', function () {
    $student = Student::factory()->create(['campus_id' => $this->campus->id]);
    Payment::factory()->create(['student_id' => $student->id, 'paid_at' => '2026-06-10 09:00:00']);

    $this->actingAs($this->user)
        ->get(route('finance.payments.index', ['date_range' => ['2026-06-01', '2026-06-30'], 'sort' => 'amount', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->component(/* the page name PaymentController::index currently renders */ 'Finance/Payments/Index')
            ->has('items.data')
            ->has('stats')
            ->where('filters.sort', 'amount')
        );
});

it('rejects a malformed date_range', function () {
    $this->actingAs($this->user)
        ->get(route('finance.payments.index', ['date_range' => 'not-an-array']))
        ->assertSessionHasErrors('date_range');
});

it('forbids the payment lookup without view_finance_payments', function () {
    grantFinance($this->user, ['view_finance_charges'], $this->campus);
    $this->actingAs($this->user)->get(route('finance.payments.index'))->assertForbidden();
});
```

> Confirm the exact component name `PaymentController::index` renders (the page passes `items`, `stats`, `filters` from `ListPaymentsQuery`); set it in the test.

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Lookup/PaymentLookupTest.php`
Expected: FAIL — no validation on `date_range` / authz not enforced via a request.

- [ ] **Step 3: Write the FormRequest**

Create `app/Modules/Finance/Http/Requests/Lookup/FilterPaymentsRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

class FilterPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_payments');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'source' => 'nullable|string',
            'status' => 'nullable|string',
            'date_range' => 'nullable|array|size:2',
            'date_range.*' => 'nullable|date',
            'sort' => 'nullable|string',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
```

- [ ] **Step 4: Wire it into the controller** — change `PaymentController::index` signature to accept the request (validation runs before the query; `ListPaymentsQuery::handle(Request)` still reads the same input):

```php
public function index(
    \App\Modules\Finance\Http\Requests\Lookup\FilterPaymentsRequest $request,
    \App\Modules\Finance\Queries\ListPaymentsQuery $query,
): \Inertia\Response {
    return \Inertia\Inertia::render(/* keep the existing component name */ 'Finance/Payments/Index', $query->handle($request));
}
```

> `$query->handle($request)` already returns `['items' => …, 'stats' => …]`; if the current controller also passes a `filters` prop, keep doing so (`$request->only([...])`). Match the existing render shape exactly.

- [ ] **Step 5: Run to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Lookup/PaymentLookupTest.php`
Expected: PASS (3 passed).

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Finance/Http/Requests/Lookup/FilterPaymentsRequest.php app/Modules/Finance/Http/Web/Admin/PaymentController.php tests/Feature/Finance/Lookup/PaymentLookupTest.php
git commit -m "feat(finance): add FilterPaymentsRequest validation for payment lookup"
```

## Task 5: Frontend shared — selection composable + "Send to Batch Studio" bar + row actions

The cross-cutting pieces the three lookup pages share: a selection set keyed by row → student id, a sticky multi-select bar that hands the selected students to a Batch Studio job (M4), and a row-action cell (row → Student 360 with a `focus` param + an optional "open detail" link for audit).

> **No JS unit runner** — verify with `./scripts/dev.sh npm run lint -- <files>` + the Task 10 smoke. Do not run whole-project `vue-tsc`.

**Files:**
- Create: `resources/js/composables/useLookupSelection.ts`
- Create: `resources/js/components/finance/lookup/SendToBatchBar.vue`
- Create: `resources/js/components/finance/lookup/LookupRowActions.vue`

- [ ] **Step 1: Write the selection composable**

Create `resources/js/composables/useLookupSelection.ts`:

```ts
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { financeRoutes } from '@/utils/routes';

export interface LookupSelectableRow {
    key: number | string; // unique row id (charge/invoice/payment id)
    studentId: number; // for the batch hand-off + 360 deep link
}

export function useLookupSelection() {
    // rowKey -> studentId
    const selected = ref<Map<number | string, number>>(new Map());

    function toggle(row: LookupSelectableRow): void {
        const next = new Map(selected.value);
        next.has(row.key) ? next.delete(row.key) : next.set(row.key, row.studentId);
        selected.value = next;
    }

    function toggleAll(rows: LookupSelectableRow[], on: boolean): void {
        const next = new Map(selected.value);
        for (const r of rows) on ? next.set(r.key, r.studentId) : next.delete(r.key);
        selected.value = next;
    }

    function isSelected(key: number | string): boolean {
        return selected.value.has(key);
    }

    function clear(): void {
        selected.value = new Map();
    }

    const count = computed(() => selected.value.size);
    const studentIds = computed(() => [...new Set([...selected.value.values()])]);

    function sendToBatch(job: 'dng' | 'reminders'): void {
        const ids = studentIds.value;
        if (ids.length === 0) return;
        const url = job === 'dng' ? financeRoutes.batchStudio.dng() : financeRoutes.batchStudio.reminders();
        // GET → student_ids land in the query string; the Batch Studio wizard seeds its setup from them.
        router.get(url, { student_ids: ids }, { preserveScroll: false });
    }

    return { selected, toggle, toggleAll, isSelected, clear, count, studentIds, sendToBatch };
}
```

> **M4 hand-off dependency (document, 2-line follow-up on M4 files):** for the carried `student_ids` to pre-fill the wizard, `BatchStudioController::dng`/`reminders` must forward `'prefillStudentIds' => array_map('intval', (array) $request->input('student_ids', []))`, and `DngPush.vue`/`Reminders.vue` must seed `defaultSetup.student_ids` from that prop. If M4 isn't merged yet, the bar still navigates correctly; the wizard simply starts empty until that 2-line passthrough lands.

- [ ] **Step 2: Write the bar**

Create `resources/js/components/finance/lookup/SendToBatchBar.vue`:

```vue
<script setup lang="ts">
import { Bell, Send, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

defineProps<{ count: number; canDng?: boolean; canRemind?: boolean }>();
const emit = defineEmits<{ (e: 'dng'): void; (e: 'reminders'): void; (e: 'clear'): void }>();
</script>

<template>
    <div
        v-if="count > 0"
        class="sticky bottom-0 z-20 flex items-center justify-between gap-4 border-t bg-background/95 px-4 py-3 backdrop-blur"
    >
        <span class="text-sm font-medium">{{ count }} sinh viên đã chọn</span>
        <div class="flex gap-2">
            <Button v-if="canDng" size="sm" variant="outline" @click="emit('dng')">
                <Send class="mr-1 h-4 w-4" /> Đẩy DNG hàng loạt
            </Button>
            <Button v-if="canRemind" size="sm" variant="outline" @click="emit('reminders')">
                <Bell class="mr-1 h-4 w-4" /> Nhắc nợ hàng loạt
            </Button>
            <Button size="sm" variant="ghost" @click="emit('clear')"><X class="h-4 w-4" /></Button>
        </div>
    </div>
</template>
```

- [ ] **Step 3: Write the row-action cell**

Create `resources/js/components/finance/lookup/LookupRowActions.vue`:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ExternalLink, FileSearch } from 'lucide-vue-next';
import { financeRoutes } from '@/utils/routes';

defineProps<{ studentId: number; focus?: string; detailUrl?: string }>();
</script>

<template>
    <div class="flex items-center justify-end gap-1">
        <Link
            :href="financeRoutes.students.overview(studentId, focus)"
            class="rounded p-1 hover:bg-muted"
            title="Mở hồ sơ SV (360)"
        >
            <ExternalLink class="h-4 w-4" />
        </Link>
        <Link v-if="detailUrl" :href="detailUrl" class="rounded p-1 hover:bg-muted" title="Chi tiết (audit)">
            <FileSearch class="h-4 w-4" />
        </Link>
    </div>
</template>
```

- [ ] **Step 4: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/composables/useLookupSelection.ts resources/js/components/finance/lookup/SendToBatchBar.vue resources/js/components/finance/lookup/LookupRowActions.vue`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useLookupSelection.ts resources/js/components/finance/lookup/SendToBatchBar.vue resources/js/components/finance/lookup/LookupRowActions.vue
git commit -m "feat(finance): add lookup selection composable + batch hand-off bar + row actions"
```

---

## Task 6: Charge Ledger page → lookup standard (the reference migration)

Migrate `Charges/Index.vue` to `useDataTable` + Table primitives + `DataPagination`, with sortable heads, sticky header, right-aligned tabular money, status chips, **row → Student 360 (`focus=charge:<id>`)**, and the selection bar. This is the reference the other two pages mirror.

**Files:**
- Modify: `resources/js/pages/Finance/Charges/Index.vue`

- [ ] **Step 1: Rewrite the page**

Replace `resources/js/pages/Finance/Charges/Index.vue` with:

```vue
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DataPagination from '@/components/DataPagination.vue';
import LookupRowActions from '@/components/finance/lookup/LookupRowActions.vue';
import SendToBatchBar from '@/components/finance/lookup/SendToBatchBar.vue';
import { useDataTable } from '@/composables/useDataTable';
import { useLookupSelection } from '@/composables/useLookupSelection';
import { formatCurrency, getChargeStatusBadgeClass, getChargeStatusLabel, getChargeTypeLabel } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';

interface ChargeRow {
    id: number;
    student_id: number;
    student: { id: number; full_name: string; student_id: string };
    charge_type: string;
    description: string;
    amount: number;
    status: string;
    semester?: { name: string };
}
interface Paginated<T> {
    data: T[];
    from: number; to: number; total: number; current_page: number; last_page: number;
    prev_page_url: string | null; next_page_url: string | null; links: unknown[]; per_page: number;
}
const props = defineProps<{
    charges: Paginated<ChargeRow>;
    semesters: { id: number; name: string }[];
    filters: { search?: string; semester_id?: number | null; charge_type?: string | null; status?: string | null; sort?: string | null; direction?: 'asc' | 'desc' | null; per_page?: number };
}>();

const { filters, handleSearch, setFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useDataTable({
    baseUrl: financeRoutes.lookup.chargeLedger(),
    initialFilters: {
        search: props.filters.search ?? '',
        semester_id: props.filters.semester_id ?? null,
        charge_type: props.filters.charge_type ?? null,
        status: props.filters.status ?? null,
        sort: props.filters.sort ?? null,
        direction: props.filters.direction ?? null,
        per_page: props.filters.per_page ?? 20,
    },
    only: ['charges', 'filters'],
});

const sel = useLookupSelection();
const perms = computed(() => ((usePage().props.auth as { permissions?: string[] }).permissions ?? []));
const canDng = computed(() => perms.value.includes('create_finance_payments'));
const canRemind = computed(() => perms.value.includes('view_finance_operations_due_calendar'));

function sortBy(col: string): void {
    handleSortChange(col, currentSort.value === col && currentDirection.value === 'asc' ? 'desc' : 'asc');
}
function openStudent(row: ChargeRow): void {
    router.visit(financeRoutes.students.overview(row.student_id, `charge:${row.id}`));
}
const allRows = computed(() => props.charges.data.map((c) => ({ key: c.id, studentId: c.student_id })));
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-4 p-6">
            <h1 class="text-2xl font-semibold">Charge Ledger (toàn campus)</h1>

            <div class="flex flex-wrap items-center gap-2">
                <Input :model-value="filters.search" placeholder="Tìm mô tả / tên / mã SV…" class="w-72" @update:model-value="handleSearch" />
                <select :value="filters.status ?? ''" class="rounded border p-2 text-sm" @change="setFilter('status', ($event.target as HTMLSelectElement).value || null)">
                    <option value="">Mọi trạng thái</option>
                    <option value="active">Active</option>
                    <option value="void">Void</option>
                    <option value="transferred">Transferred</option>
                </select>
                <select :value="filters.semester_id ?? ''" class="rounded border p-2 text-sm" @change="setFilter('semester_id', Number(($event.target as HTMLSelectElement).value) || null)">
                    <option value="">Mọi kỳ</option>
                    <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="sticky top-0 z-10 w-8 bg-background">
                            <Checkbox @update:model-value="(v) => sel.toggleAll(allRows, !!v)" />
                        </TableHead>
                        <TableHead class="sticky top-0 z-10 bg-background">Sinh viên</TableHead>
                        <TableHead class="sticky top-0 z-10 cursor-pointer bg-background" @click="sortBy('charge_type')">Loại phí <ChevronsUpDown class="inline h-3 w-3" /></TableHead>
                        <TableHead class="sticky top-0 z-10 bg-background">Mô tả</TableHead>
                        <TableHead class="sticky top-0 z-10 cursor-pointer bg-background text-right" @click="sortBy('amount')">Số tiền <ChevronsUpDown class="inline h-3 w-3" /></TableHead>
                        <TableHead class="sticky top-0 z-10 cursor-pointer bg-background" @click="sortBy('status')">Trạng thái <ChevronsUpDown class="inline h-3 w-3" /></TableHead>
                        <TableHead class="sticky top-0 z-10 bg-background text-right">Thao tác</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="c in charges.data" :key="c.id" class="cursor-pointer hover:bg-muted/50" @click="openStudent(c)">
                        <TableCell @click.stop><Checkbox :model-value="sel.isSelected(c.id)" @update:model-value="() => sel.toggle({ key: c.id, studentId: c.student_id })" /></TableCell>
                        <TableCell><div class="font-medium">{{ c.student.full_name }}</div><div class="text-xs text-muted-foreground tabular-nums">{{ c.student.student_id }}</div></TableCell>
                        <TableCell>{{ getChargeTypeLabel(c.charge_type) }}</TableCell>
                        <TableCell class="max-w-[220px] truncate text-muted-foreground">{{ c.description }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ formatCurrency(c.amount) }}</TableCell>
                        <TableCell><Badge :class="getChargeStatusBadgeClass(c.status)">{{ getChargeStatusLabel(c.status) }}</Badge></TableCell>
                        <TableCell @click.stop><LookupRowActions :student-id="c.student_id" :focus="`charge:${c.id}`" /></TableCell>
                    </TableRow>
                    <TableEmpty v-if="charges.data.length === 0" :colspan="7">Không có khoản phí nào khớp bộ lọc.</TableEmpty>
                </TableBody>
            </Table>

            <DataPagination :pagination="charges" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            <SendToBatchBar :count="sel.count.value" :can-dng="canDng" :can-remind="canRemind" @dng="sel.sendToBatch('dng')" @reminders="sel.sendToBatch('reminders')" @clear="sel.clear" />
        </div>
    </AppLayout>
</template>
```

> If `charge_type` filter options are needed as a dropdown, reuse the `chargeTypes` prop (the controller already passes it) — add a `<select>` mirroring the status one. Confirm `TableEmpty` accepts a `colspan` prop in this repo; if not, render a manual empty `<TableRow><TableCell :colspan="7">…</TableCell></TableRow>`.

- [ ] **Step 2: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/pages/Finance/Charges/Index.vue`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Finance/Charges/Index.vue
git commit -m "feat(finance): migrate Charge Ledger to lookup standard (sort, row→360, batch select)"
```

---

## Task 7: Invoices page → lookup standard

Mirror Task 6 for `Invoices/Index.vue`: `useDataTable` + sortable heads + sticky header + **row → 360 (`focus=invoice:<id>`)** + selection bar, while **keeping the existing Excel export** button (`finance.invoices.export`).

**Files:**
- Modify: `resources/js/pages/Finance/Invoices/Index.vue`

- [ ] **Step 1: Rewrite the page** — same structure as Task 6 with these deltas:
  - `baseUrl: financeRoutes.lookup.invoices()`, `only: ['invoices', 'filters']`.
  - Row interface: `{ id, invoice_number, student_id, student, semester?, real_time_status, total_amount, paid_amount, outstanding_balance, due_date }`.
  - Columns: Invoice # (link to `finance.invoices.show` via `LookupRowActions :detail-url`), Student, Semester, Total / Paid / Outstanding (right-aligned `formatCurrency`), Status chip (`getStatusBadgeVariant` / the invoice status map), Due date (`formatDate`), Actions.
  - Sortable heads: `invoice_number`, `due_date`, `cached_total_amount`, `cached_paid_amount` (the SQL columns — **not** the appended `outstanding_balance`, which isn't a column; show it but don't make its head sortable).
  - Row click → `router.visit(financeRoutes.students.overview(row.student_id, 'invoice:' + row.id))`.
  - Keep the existing **Export Excel** `<Button>` linking to `route('finance.invoices.export')` with the current query string preserved.
  - `LookupRowActions :student-id="row.student_id" :focus="'invoice:' + row.id" :detail-url="route('finance.invoices.show', { invoice: row.id })"`.

```ts
// useDataTable setup (mirror Task 6)
const { filters, handleSearch, setFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useDataTable({
    baseUrl: financeRoutes.lookup.invoices(),
    initialFilters: {
        search: props.filters.search ?? '',
        semester_id: props.filters.semester_id ?? null,
        status: props.filters.status ?? null,
        sort: props.filters.sort ?? null,
        direction: props.filters.direction ?? null,
        per_page: props.filters.per_page ?? 50,
    },
    only: ['invoices', 'filters'],
});
```

- [ ] **Step 2: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/pages/Finance/Invoices/Index.vue`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Finance/Invoices/Index.vue
git commit -m "feat(finance): migrate Invoices to lookup standard (sort, row→360, batch select, keep export)"
```

---

## Task 8: Payments page → lookup standard + date-range filter UI

Migrate `Payments/Index.vue` from `useInertiaFilters` to `useDataTable`, change the row click from `finance.payments.show` to **Student 360 (`focus=payment:<id>`)**, add the **date-range filter UI** (the backend `ListPaymentsQuery` already supports `date_range`), and keep the stats cards.

**Files:**
- Modify: `resources/js/pages/Finance/Payments/Index.vue`

- [ ] **Step 1: Rewrite the page** — same structure as Task 6 with these deltas:
  - `baseUrl: financeRoutes.collect.payments()`, `only: ['items', 'stats', 'filters']`.
  - Props: `items: Paginated<PaymentRow>`, `stats: { payment_count, total_paid, total_applied, total_unapplied }`, `filters`.
  - Keep the stats cards (reuse `StatsCard.vue` if present, else the inline grid).
  - Columns + sortable heads (the query whitelist): `paid_at` (date), `student_id`, `student_name`, `amount` (right), `unapplied_amount` (right) → `sortBy('paid_at'|'student_id'|'student_name'|'amount'|'unapplied_amount')`.
  - **Date-range UI:** two date inputs bound to `filters.date_range[0]` / `[1]`, committing with `setFilter('date_range', [from, to])`:

```vue
<div class="flex items-center gap-2">
    <input type="date" :value="(filters.date_range ?? [])[0] ?? ''"
        class="rounded border p-2 text-sm"
        @change="setFilter('date_range', [($event.target as HTMLInputElement).value, (filters.date_range ?? [])[1] ?? ''])" />
    <span class="text-muted-foreground">→</span>
    <input type="date" :value="(filters.date_range ?? [])[1] ?? ''"
        class="rounded border p-2 text-sm"
        @change="setFilter('date_range', [(filters.date_range ?? [])[0] ?? '', ($event.target as HTMLInputElement).value])" />
</div>
```

  - Row click → `router.visit(financeRoutes.students.overview(row.student.id, 'payment:' + row.id))`.
  - Selection key = `row.id`, studentId = `row.student.id`; the SendToBatchBar's "Nhắc nợ" is the natural action from payments (unallocated follow-up); keep both buttons gated by perms.
  - `initialFilters` includes `date_range: props.filters.date_range ?? null` and `source`, `status`, `sort: props.filters.sort ?? 'paid_at'`, `direction: props.filters.direction ?? 'desc'`.

- [ ] **Step 2: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/pages/Finance/Payments/Index.vue`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/Finance/Payments/Index.vue
git commit -m "feat(finance): migrate Payments to lookup standard (useDataTable, date-range, row→360)"
```

## Task 9: Audit Workspace as the drilldown destination — money-flow graph + timeline polish

Today the Audit Workspace renders the graph as a **count-only summary** (node-group badges + edge count). Build a lightweight node/edge **money-flow panel** from the existing `graph.nodes` / `graph.edges` (no graph-drawing library — a grouped columnar layout), polish the signed-ledger timeline coloring (+ green / − red), and accept a `finding_code` deep-link banner. **Backend is unchanged** — `GetFinanceAuditGraphQuery` already returns nodes/edges/derived_balance/ledger_entries; the §4.4 drilldown *params/resolver* remain M3's.

**Files:**
- Create: `resources/js/components/finance/audit/MoneyFlowGraph.vue`
- Modify: `resources/js/pages/Finance/Audit/Workspace.vue`

- [ ] **Step 1: Write the money-flow panel**

Create `resources/js/components/finance/audit/MoneyFlowGraph.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { formatCurrency } from '@/types/finance';

interface GraphNode { key: string; type: string; id: number; label: string; status?: string | null; amount?: number | null; date?: string | null }
interface GraphEdge { from: string; to: string; kind: string; amount?: number | null }

const props = defineProps<{ graph: { nodes: GraphNode[]; edges: GraphEdge[] } }>();

const COLUMN_ORDER = ['student', 'invoice', 'invoice_line', 'charge', 'payment', 'dng'] as const;
const COLUMN_LABEL: Record<string, string> = {
    student: 'Sinh viên', invoice: 'Invoice', invoice_line: 'Dòng phí', charge: 'Charge', payment: 'Thanh toán', dng: 'DNG',
};

const columns = computed(() =>
    COLUMN_ORDER
        .map((type) => ({ type, label: COLUMN_LABEL[type] ?? type, nodes: props.graph.nodes.filter((n) => n.type === type) }))
        .filter((c) => c.nodes.length > 0),
);

const nodeByKey = computed(() => new Map(props.graph.nodes.map((n) => [n.key, n])));

const edgesByKind = computed(() => {
    const m = new Map<string, GraphEdge[]>();
    for (const e of props.graph.edges) {
        const arr = m.get(e.kind) ?? [];
        arr.push(e);
        m.set(e.kind, arr);
    }
    return [...m.entries()].map(([kind, edges]) => ({ kind, edges }));
});

function label(key: string): string {
    return nodeByKey.value.get(key)?.label ?? key;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid gap-3" :style="{ gridTemplateColumns: `repeat(${columns.length}, minmax(0, 1fr))` }">
            <div v-for="col in columns" :key="col.type" class="flex flex-col gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ col.label }} ({{ col.nodes.length }})</p>
                <div v-for="n in col.nodes" :key="n.key" class="rounded-lg border p-2 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate font-medium">{{ n.label }}</span>
                        <Badge v-if="n.status" variant="outline" class="shrink-0 text-xs">{{ n.status }}</Badge>
                    </div>
                    <p v-if="n.amount != null" class="text-right tabular-nums text-muted-foreground">{{ formatCurrency(n.amount) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border p-3">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Liên kết dòng tiền</p>
            <div v-for="g in edgesByKind" :key="g.kind" class="mb-2 last:mb-0">
                <p class="text-sm font-medium">{{ g.kind }} ({{ g.edges.length }})</p>
                <ul class="ml-3 list-disc text-sm text-muted-foreground">
                    <li v-for="(e, i) in g.edges" :key="i" class="tabular-nums">
                        {{ label(e.from) }} → {{ label(e.to) }}<span v-if="e.amount != null"> · {{ formatCurrency(e.amount) }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
```

> Pragmatic, library-free "full graph": all nodes (grouped in money-flow column order) + all edges (grouped by kind). A force-directed/SVG visualization is explicitly out of scope (a possible follow-up) — this delivers the design's "audit graph đầy đủ" as the drilldown destination without a new dependency.

- [ ] **Step 2: Wire it into the Workspace** — in `resources/js/pages/Finance/Audit/Workspace.vue`:
  1. Import the component: `import MoneyFlowGraph from '@/components/finance/audit/MoneyFlowGraph.vue';`
  2. Replace the **graph-summary card body** (the node-group-badges + edge-count block) with the panel, keeping the existing `<Deferred data="graph">` wrapper + skeleton fallback:

```vue
<Deferred data="graph">
    <template #fallback><div class="h-40 animate-pulse rounded-lg bg-muted" /></template>
    <MoneyFlowGraph v-if="graph" :graph="graph" />
</Deferred>
```

  3. Polish the **signed-ledger timeline** rows: color `signed_amount` green when `> 0`, red when `< 0`, and render the sign explicitly:

```vue
<span class="tabular-nums" :class="event.signed_amount < 0 ? 'text-red-600' : 'text-emerald-600'">
    {{ event.signed_amount < 0 ? '−' : '+' }}{{ formatCurrency(Math.abs(event.signed_amount)) }}
</span>
```

  4. Add a **deep-link banner** when arriving from an invariant finding (M3 passes `finding_code` in the query; render read-only, no resolution logic here):

```vue
<Alert v-if="filters.finding_code" class="mb-3">
    <AlertDescription>Đang xem theo phát hiện <strong>{{ filters.finding_code }}</strong>. Bảng "Sức khỏe dữ liệu" (Cockpit) là nguồn của lối tắt này.</AlertDescription>
</Alert>
```

> The `filters.finding_code` is simply read from props if present; if M3 hasn't shipped the param, `filters.finding_code` is `undefined` and the banner never renders — no coupling, no breakage. Add `finding_code?: string` to the page's `filters` prop type.

- [ ] **Step 3: Lint**

Run: `./scripts/dev.sh npm run lint -- resources/js/components/finance/audit/MoneyFlowGraph.vue resources/js/pages/Finance/Audit/Workspace.vue`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/finance/audit/MoneyFlowGraph.vue resources/js/pages/Finance/Audit/Workspace.vue
git commit -m "feat(finance): render full money-flow graph + signed-timeline polish in Audit Workspace"
```

---

## Task 10: Nav verification + milestone evidence

The "🔎 Tra cứu & Audit" sidebar group already contains Audit Workspace + Charge Ledger + Invoices (Payments correctly lives in "💳 Thu & Đối soát" per §3.2). Verify no nav change is needed, then prove the milestone end-to-end. M5 is read-only, so the invariant run is a **sanity check**, not a gate.

**Files:**
- Verify (likely no change): `resources/js/constants/menu-sidebar.ts`
- Modify: `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md`

- [ ] **Step 1: Verify nav** — confirm `menu-sidebar.ts` "Tra cứu & Audit" group already links `financeRoutes.audit()`, `financeRoutes.lookup.chargeLedger()`, `financeRoutes.lookup.invoices()` and that "Payments" sits under "Thu & Đối soát" (`financeRoutes.collect.payments()`). No edit expected; if a link is missing, add it mirroring the existing entries (icon + `requiredPermissions`).

- [ ] **Step 2: Run the full Lookup backend suite**

Run: `./scripts/dev.sh test tests/Feature/Finance/Lookup`
Expected: all green (charges 4 + invoices 2 + payments 3 + any authz).

- [ ] **Step 3: Lint all changed frontend files**

Run: `./scripts/dev.sh npm run lint -- resources/js/pages/Finance/Charges/Index.vue resources/js/pages/Finance/Invoices/Index.vue resources/js/pages/Finance/Payments/Index.vue resources/js/pages/Finance/Audit/Workspace.vue resources/js/components/finance/lookup resources/js/components/finance/audit/MoneyFlowGraph.vue resources/js/composables/useLookupSelection.ts`
Expected: no errors.

- [ ] **Step 4: Browser smoke** — `./scripts/dev.sh start`, then as a finance operator:
  1. **Charge Ledger:** filter by status/semester, search, click a sortable head (amount/status) → server re-sorts; click a row → lands on Student 360 focused on that charge; select 2+ rows → "Đẩy DNG hàng loạt" appears (only if `create_finance_payments`) → navigates to Batch Studio carrying the student IDs.
  2. **Invoices:** sort by due_date; row → 360 (`focus=invoice:`); Excel export still works; selection bar works.
  3. **Payments:** set a date range → list filters by `paid_at`; sort by amount/unallocated; row → 360 (`focus=payment:`); stats cards present.
  4. **Audit Workspace:** search a student → the money-flow graph renders columns + edge list; the signed timeline shows +green/−red; the derived-balance drift highlight still works; (if M3 merged) arriving via an invariant finding shows the `finding_code` banner.

- [ ] **Step 5: Invariant sanity check (read-only milestone)**

Run: `./scripts/dev.sh artisan finance:audit-invariants`
Expected: counts **unchanged** vs a pre-M5 run (M5 writes no money). If anything changed, a "read-only" task accidentally mutated state — STOP and investigate.

- [ ] **Step 6: Record evidence** — append an "M5 Lookup & Audit acceptance" section to the umbrella story's `validation.md`: the Lookup suite result (Step 2), the lint pass (Step 3), the four smoke outcomes (Step 4), and the invariant sanity result (Step 5). Mirror the M1/M2 evidence format.

- [ ] **Step 7: Commit**

```bash
git add docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md
git commit -m "docs(finance): record M5 Lookup & Audit acceptance evidence"
```

---

## Self-Review

Checked against the design spec (§3.2 sidebar IA, §4.4 drilldown destination, §5.4/§7.1 signed ledger, §7.3 dense-table standard, §7.4 colors, §10 milestone 5) with fresh eyes:

**1. Spec coverage**
- §3.2 "object-lists demoted to filters/lookup" → Charges/Invoices/Payments converge on one lookup standard (Tasks 6–8); Payments stays in the "Thu & Đối soát" group, Charges/Invoices in "Tra cứu & Audit" (Task 10 verify). ✓
- §7.3 dense-table standard: server sort/filter/paginate (`useDataTable` + whitelisted-sort queries, Tasks 1/3/4/6/8) ✓; sticky header (`sticky top-0` on `TableHead`) ✓; right-aligned tabular money (`text-right tabular-nums` + `formatCurrency`) ✓; status chips (existing badge maps) ✓; **row → 360** (`LookupRowActions` + row click, `focus=type:id`) ✓; **multi-select → Batch Studio** (`useLookupSelection` + `SendToBatchBar`, Task 5) ✓; empty state (`TableEmpty`) ✓; loading (`useDataTable.isLoading`) — available, wire a spinner if desired ⚠️ documented.
- §4.4 / §10 "audit graph đầy đủ as drilldown destination" → `MoneyFlowGraph` renders all nodes+edges (Task 9); drilldown **params/resolver stay M3's** and are consumed read-only (banner only). ✓
- §5.4/§7.1 signed ledger → Audit timeline +green/−red polish (Task 9); the per-student signed ledger lens already lives in Student 360 (`LedgerLens`), so the global Charge Ledger stays a flat dense table (not a per-student ledger) — intentional, noted. ✓
- §7.4 semantic colors → reuse existing `*_BADGE_CLASSES` maps; no new color semantics. ✓

**2. Placeholder scan** — backend tasks (0–4) have full code + tests; Task 5 + Task 6 + Task 9 are shown in full; Tasks 7–8 give explicit deltas against the full Task 6 reference (columns, baseUrl, sort whitelist, date-range snippet) — acceptable since the reference page is complete. No "TBD"/"add validation"/"handle edge cases". Every "verify before writing" note names a concrete symbol to confirm.

**3. Type/name consistency** — `useLookupSelection` (`toggle`/`toggleAll`/`isSelected`/`clear`/`count`/`studentIds`/`sendToBatch`), `LookupSelectableRow {key, studentId}`, `financeRoutes.lookup.chargeLedger|invoices` + `collect.payments` + `students.overview(id, focus)`, focus format `"<type>:<id>"`, `List*Query::handle(Request): {items}`, `Filter*Request`, `useDataTable` return names (`handleSearch`/`setFilter`/`handleSortChange`/`handlePaginationNavigate`/`handlePageSizeChange`/`currentSort`/`currentDirection`) — consistent across backend, composable, and all three pages. ✓

No spec requirement is left without a task.

## Unresolved Questions

- **Sorting appended computed columns:** `outstanding_balance` (invoices) and `unapplied_amount` (payments, already handled via a subquery in `ListPaymentsQuery`) aren't plain columns. Invoices: `outstanding_balance` is displayed but **not** server-sortable (only `cached_total_amount`/`cached_paid_amount`/`due_date`/`invoice_number` are). Confirm that's acceptable, or add a derived SQL expression to sort by outstanding.
- **`chargeTypes` options source:** Task 2 must reuse the *exact* accessor the current `FinanceChargeController::index` uses to build `chargeTypes` (don't invent `ChargeTypeOptions::all()`); confirm the real call before rewriting.
- **M4 hand-off passthrough:** the "Send to Batch Studio" navigation carries `student_ids`, but pre-filling the wizard needs a 2-line forward in `BatchStudioController::dng`/`reminders` + `DngPush.vue`/`Reminders.vue` (M4 files). Confirm M4 merge order, or land that passthrough as a tiny M4 follow-up.
- **M3 invariant-drilldown params:** the `finding_code` banner is read-only and harmless if absent. Confirm whether M3 (which *owns* `finding_code`/`scope`/`sample_id` + `FinanceInvariantSampleResolver`) lands before or after M5; M5 must not implement those params (design §10).
- **`TableEmpty` colspan + `useDataTable` loading slot:** confirm `TableEmpty` accepts `colspan` in this repo (else use a manual empty row) and decide whether to surface `isLoading` as a row-overlay spinner on all three tables for the §7.3 "loading state always explicit" rule.
- **Global Charge Ledger signed-ledger lens:** kept as a flat table (per-student signed ledger already in 360). Confirm no desire for a signed-ledger toggle on the global table (would be scope creep beyond §10's "mostly reuse").

---

**Plan complete and saved to `docs/superpowers/plans/2026-06-15-finance-office-lookup-and-audit.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints for review.

**Which approach?**

