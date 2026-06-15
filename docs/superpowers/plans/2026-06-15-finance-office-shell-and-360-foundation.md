# Finance Office Shell + Student 360 Foundation — Implementation Plan (Milestone 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the Finance Office operator-console foundation — a finance-aware app shell (global ⌘K search + semester switcher), a work-organized 5-group sidebar, finance route constants, and a minimal Student 360 route shell that search lands on — without touching money-write logic.

**Architecture:** Read-only + presentation only. New read-model controllers/queries reuse existing Finance services (`GetStudentBalanceQuery`, `ResolveFinanceAuditSearchQuery`, `GetFinanceAuditGraphQuery`, `FinanceLedgerTimelineBuilder`, `SettlementService`). The 360 shell is a new Inertia page modeled on the just-shipped S-009 Audit Workspace controller/page. Global search is a JSON endpoint (`ApiResponse`) consumed by a ⌘K palette that navigates to the 360. Semester selection becomes a shared Inertia prop + session value (single source), mirroring the existing campus pattern.

**Tech Stack:** Laravel 13, Inertia v3 (`@inertiajs/vue3 ^3.0`), Vue 3 + `<script setup lang="ts">`, Tailwind v4, Ziggy (`ziggy-js`) route helper, Pest (backend tests), shadcn-vue UI primitives (`@/components/ui/*`), `lucide-vue-next` icons.

**Story:** `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/` (`FIN-REV-010`). Design source: `docs/features/finance/finance-office-ux-redesign-design.md` §10 milestone 1. **Portal impact: none** (admin/staff web only; does not touch `/api/v1/student/*` or `/api/v1/lecturer/*`).

---

## Testing approach (repo reality — read before starting)

This repo's automated test signal is **backend Pest only**. Confirmed from `package.json`: there is **no JS unit-test runner** (no vitest/jest); frontend scripts are `dev`, `build`, `lint` (eslint), `format:check` (prettier), `type-check` (`vue-tsc`). Therefore:

- **Backend tasks (controllers, endpoints, shared props, permissions):** TDD with Pest feature tests. Run inside Docker: `./scripts/dev.sh test <path>`. Follow the exact pattern in `tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php` — `uses(RefreshDatabase::class)`, a `grant…()` helper that mocks `PermissionService::getUserPermissions`, `session(['current_campus_id' => …])` + `app()->singleton('campus', fn () => $campus)`, then `actingAs($user)->get(...)->assertInertia(...)`.
- **Frontend tasks (Vue/TS):** no unit test exists to write. "Verify" = `./scripts/dev.sh npm run lint` on the changed files + a **browser smoke** at the end of the milestone. ⚠️ Do **not** run whole-project `npm run type-check` inside the dev container — `vue-tsc --noEmit` is SIGKILL'd (OOM) there; rely on eslint per file and let CI/host run the full type-check. Where a frontend change has a backend contract (search endpoint, shared prop), the Pest test covers the contract.
- **Commands** assume `./scripts/dev.sh` Docker wrappers (per `AGENTS.md`). Never run bare `php artisan` / `npm`.

After each task that changes money/DNG-visible surfaces, no `finance:audit-invariants` evidence is required because **no money state changes** in Milestone 1 (read-only). Record that explicitly in the trace.

---

## File Structure (created / modified in this milestone)

**Created — backend:**
- `app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php` — Student 360 shell page (identity + 4 balances + deferred ledger).
- `app/Modules/Finance/Http/Web/Admin/FinanceGlobalSearchController.php` — JSON `ApiResponse` search resolver for ⌘K.
- `app/Modules/Finance/Http/Web/Admin/FinanceSemesterContextController.php` — sets session semester, redirects back.
- `app/Modules/Finance/Http/Requests/Student360ShowRequest.php` — validates `focus` query param.
- `tests/Feature/Finance/Student360/StudentOverviewShellTest.php`
- `tests/Feature/Finance/Search/FinanceGlobalSearchTest.php`
- `tests/Feature/Finance/Shell/SemesterContextTest.php`

**Created — frontend:**
- `resources/js/constants/finance-routes.ts` — `FINANCE_ROUTE_NAMES`.
- `resources/js/pages/Finance/Student360/Show.vue` — the 360 shell page.
- `resources/js/components/finance/FinanceCommandPalette.vue` — global ⌘K search.
- `resources/js/components/finance/SemesterSwitcher.vue` — topbar semester selector.
- `resources/js/types/finance.ts` — shared TS types (`SemesterContext`, `Student360`, search result).

**Modified — backend:**
- `app/Modules/Finance/routes/web.php` — register 360, search, semester-context routes.
- `config/permission.php` — add `view_finance_student_overview`, `view_finance_all_campus`.
- `database/seeders/InitialSetup/RoleAndPermissionSeeder.php` — map new perms to finance roles.
- `app/Http/Middleware/HandleInertiaRequests.php` — add shared `semester` prop.

**Modified — frontend:**
- `resources/js/utils/routes.ts` — add `financeRoutes`.
- `resources/js/constants/menu-sidebar.ts` — restructure Finance Office into 5 work-groups + keep Discounts & Funding.
- `resources/js/components/AppSidebarHeader.vue` — mount `FinanceCommandPalette` + `SemesterSwitcher` (permission-gated).
- `resources/js/types/index.ts` (or wherever `SharedData`/`PageProps` lives) — extend with `semester?: SemesterContext`.

**Modified — docs/harness:**
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/` — record the menu-migration table + Milestone-1 acceptance evidence.

---

## Task 1: Finance route constants (`financeRoutes`)

Removes the literal-URL anti-pattern (`docs/rules/frontend.md` §7: "Always use `route('name')` … never literal URL strings"). The Finance Office menu is currently 100% literal URLs; every later task and the IA rewrite depend on a route helper existing first.

**Files:**
- Create: `resources/js/constants/finance-routes.ts`
- Modify: `resources/js/utils/routes.ts` (add `financeRoutes`; mirror the `system-routes.ts` import style at line 23)

- [ ] **Step 1: Create the route-name constants**

Create `resources/js/constants/finance-routes.ts` (mirrors `resources/js/constants/system-routes.ts` `*_ROUTE_NAMES` + `as const` pattern). Names match `app/Modules/Finance/routes/web.php` (group `name('finance.')`):

```typescript
export const FINANCE_ROUTE_NAMES = {
    // Lookup & Audit
    AUDIT_INDEX: 'finance.audit.index',
    CHARGES_INDEX: 'finance.charges.index',
    INVOICES_INDEX: 'finance.invoices.index',
    // Today
    OPERATIONS_DASHBOARD: 'finance.operations.dashboard',
    // Fee generation
    MAJOR_CHARGES_INDEX: 'finance.major.charges.index',
    OPERATIONS_GENERATE_CHARGES: 'finance.operations.generate-charges',
    EGC_CHARGES_INDEX: 'finance.egc.charges.index',
    EGC_BLOCK_RESULTS_INDEX: 'finance.egc.block-results.index',
    EGC_RETAKE_ADJUSTMENTS_INDEX: 'finance.egc.retake-adjustments.index',
    EGC_CARRY_FORWARD_INDEX: 'finance.egc.carry-forward.index',
    // Collect & reconcile
    DNG_WORKLIST: 'finance.operations.dng-worklist',
    DNG_PAYMENT_REQUESTS_INDEX: 'finance.dng.payment-requests.index',
    DNG_WEBHOOK_EVENTS_INDEX: 'finance.dng.webhook-events.index',
    SETTLEMENT_INDEX: 'finance.operations.settlement.index',
    PAYMENTS_INDEX: 'finance.payments.index',
    DUE_CALENDAR: 'finance.operations.due-calendar',
    // Exceptions
    OPERATIONS_EXCEPTIONS: 'finance.operations.exceptions',
    LIFECYCLE_EXCEPTIONS: 'finance.operations.lifecycle-exceptions',
    LIFECYCLE_EXCEPTION_HISTORY: 'finance.operations.lifecycle-exception-history',
    // New in this milestone
    STUDENT_OVERVIEW: 'finance.students.overview',
    GLOBAL_SEARCH: 'finance.search',
    SEMESTER_CONTEXT_UPDATE: 'finance.semester-context.update',
} as const;
```

- [ ] **Step 2: Add `financeRoutes` to the shared route util**

In `resources/js/utils/routes.ts`, add the import next to the existing `system-routes` import (line 23 `import { … } from '@/constants/system-routes';`):

```typescript
import { FINANCE_ROUTE_NAMES } from '@/constants/finance-routes';
```

Then append a `financeRoutes` export (same shape as `systemRoutes`, using the ziggy `route()` already imported at line 24):

```typescript
// Finance Office Routes
export const financeRoutes = {
    audit: () => route(FINANCE_ROUTE_NAMES.AUDIT_INDEX),
    today: {
        dashboard: () => route(FINANCE_ROUTE_NAMES.OPERATIONS_DASHBOARD),
    },
    feeGeneration: {
        majorCharges: () => route(FINANCE_ROUTE_NAMES.MAJOR_CHARGES_INDEX),
        batchCharges: () => route(FINANCE_ROUTE_NAMES.OPERATIONS_GENERATE_CHARGES),
        egcCharges: () => route(FINANCE_ROUTE_NAMES.EGC_CHARGES_INDEX),
        egcBlockResults: () => route(FINANCE_ROUTE_NAMES.EGC_BLOCK_RESULTS_INDEX),
        egcRetakeAdjustments: () => route(FINANCE_ROUTE_NAMES.EGC_RETAKE_ADJUSTMENTS_INDEX),
        egcCarryForward: () => route(FINANCE_ROUTE_NAMES.EGC_CARRY_FORWARD_INDEX),
    },
    collect: {
        dngWorklist: () => route(FINANCE_ROUTE_NAMES.DNG_WORKLIST),
        dngPaymentRequests: () => route(FINANCE_ROUTE_NAMES.DNG_PAYMENT_REQUESTS_INDEX),
        dngWebhookEvents: () => route(FINANCE_ROUTE_NAMES.DNG_WEBHOOK_EVENTS_INDEX),
        settlement: () => route(FINANCE_ROUTE_NAMES.SETTLEMENT_INDEX),
        payments: () => route(FINANCE_ROUTE_NAMES.PAYMENTS_INDEX),
        dueReminders: () => route(FINANCE_ROUTE_NAMES.DUE_CALENDAR),
    },
    exceptions: {
        queue: () => route(FINANCE_ROUTE_NAMES.OPERATIONS_EXCEPTIONS),
        lifecycle: () => route(FINANCE_ROUTE_NAMES.LIFECYCLE_EXCEPTIONS),
        lifecycleHistory: () => route(FINANCE_ROUTE_NAMES.LIFECYCLE_EXCEPTION_HISTORY),
    },
    lookup: {
        chargeLedger: () => route(FINANCE_ROUTE_NAMES.CHARGES_INDEX),
        invoices: () => route(FINANCE_ROUTE_NAMES.INVOICES_INDEX),
    },
    students: {
        overview: (studentId: number, focus?: string) =>
            route(FINANCE_ROUTE_NAMES.STUDENT_OVERVIEW, focus ? { student: studentId, focus } : { student: studentId }),
    },
    search: () => route(FINANCE_ROUTE_NAMES.GLOBAL_SEARCH),
    semesterContext: {
        update: () => route(FINANCE_ROUTE_NAMES.SEMESTER_CONTEXT_UPDATE),
    },
} as const;
```

> Note: `STUDENT_OVERVIEW`, `GLOBAL_SEARCH`, `SEMESTER_CONTEXT_UPDATE` ziggy names do not exist until Tasks 3–5 register them. That is fine — they are only invoked from code shipped in those tasks. Do not call `financeRoutes.students.overview()` / `.search()` / `.semesterContext.update()` from the menu (Task 6) — the menu only uses already-registered names.

- [ ] **Step 3: Lint the two files**

Run: `./scripts/dev.sh npm run lint -- resources/js/constants/finance-routes.ts resources/js/utils/routes.ts`
Expected: no errors (eslint may auto-fix import order).

- [ ] **Step 4: Commit**

```bash
git add resources/js/constants/finance-routes.ts resources/js/utils/routes.ts
git commit -m "feat(finance): add finance route name constants and helper"
```

---

## Task 2: New finance permissions + seed

Adds the two permissions the 360 shell and search/scope need. Per `docs/rules` + design §8, every new permission must be declared in `config/permission.php`, synced into the DB, and mapped to roles — not just named in a doc.

**Files:**
- Modify: `config/permission.php` (the `'finances' => [...]` block)
- Modify: `database/seeders/InitialSetup/RoleAndPermissionSeeder.php` (finance role mappings)

- [ ] **Step 1: Declare the permissions**

In `config/permission.php`, inside the `'finances'` array (currently ends with the EGC/audit/retake entries), add:

```php
    // Staff workspace foundation (S-010 milestone 1)
    'view_finance_student_overview' => 'view_finance_student_overview',
    'view_finance_all_campus' => 'view_finance_all_campus',
```

- [ ] **Step 2: Map to roles**

In `database/seeders/InitialSetup/RoleAndPermissionSeeder.php`, find the `$rolePermissions` array. Add `view_finance_student_overview` to the same finance-capable roles that already receive `view_finance_audit_workspace` (search the file for `view_finance_audit_workspace`; if it is not present in any non-super_admin role yet, add `view_finance_student_overview` to the `truong_phong` and `can_bo` finance permission lists). Leave `view_finance_all_campus` assigned to **super_admin only** (it is a privilege-escalation scope). `super_admin` already gets every permission via the `sync($allPermissionIds)` call, so no explicit line is needed there.

Example (add to the existing finance permission arrays for `truong_phong` and `can_bo`):

```php
'view_finance_student_overview',
```

- [ ] **Step 3: Sync permissions into the DB**

Run: `./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder`
Expected: output reports created permissions including `view_finance_student_overview` and `view_finance_all_campus`; no orphan deletions of existing finance perms.

- [ ] **Step 4: Verify the permissions exist**

Run: `./scripts/dev.sh artisan tinker --execute="echo \Spatie\Permission\Models\Permission::whereIn('name',['view_finance_student_overview','view_finance_all_campus'])->count();"`
Expected: prints `2`.

> If the project's permission model is not `Spatie\Permission\Models\Permission`, grep `app/Services/PermissionService.php` for the model class and adjust the tinker line. The seeders are the source of truth; this step is only a sanity check.

- [ ] **Step 5: Commit**

```bash
git add config/permission.php database/seeders/InitialSetup/RoleAndPermissionSeeder.php
git commit -m "feat(finance): add student-overview and all-campus permissions"
```

---

## Task 3: Student 360 minimal route shell

The dependency root for global search: search needs a real destination. This is a read-only page showing identity (status + lifecycle flag), the 4 core balances from `GetStudentBalanceQuery` (no money math in the controller), and a deferred basic ledger from `GetFinanceAuditGraphQuery` → `FinanceLedgerTimelineBuilder`. Campus-scoped + permission-gated, modeled on `FinanceAuditWorkspaceController`. Accepts `?focus=<type>:<id>` (echoed to the page now; deep-scroll/highlight behavior is Milestone 2).

**Files:**
- Create: `app/Modules/Finance/Http/Requests/Student360ShowRequest.php`
- Create: `app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php`
- Create: `resources/js/pages/Finance/Student360/Show.vue`
- Create: `resources/js/types/finance.ts`
- Modify: `app/Modules/Finance/routes/web.php`
- Test: `tests/Feature/Finance/Student360/StudentOverviewShellTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Student360/StudentOverviewShellTest.php`. Mirror the audit test's setup helpers verbatim (the `grant…()` permission mock + campus session):

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

if (! function_exists('grantFinanceOverview')) {
    function grantFinanceOverview(array $codes): User
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
    $this->semester = Semester::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

function makeOverviewStudent(Campus $campus, Program $program, Semester $semester): Student
{
    return Student::factory()->forCampus($campus)->forProgram($program)
        ->state(['intake' => 1, 'intake_semester_id' => $semester->id])
        ->create();
}

it('renders the 360 shell with identity and four balances for a visible student', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Student360/Show')
            ->where('student.id', $student->id)
            ->where('student.student_code', $student->student_id)
            ->has('student.lifecycle_reason')
            ->has('balances.net_charges')
            ->has('balances.total_paid')
            ->has('balances.balance')
            ->has('balances.unapplied_credit')
            ->where('focus', null));
});

it('echoes a valid focus target', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}?focus=dng:7781")
        ->assertInertia(fn ($page) => $page
            ->where('focus.type', 'dng')
            ->where('focus.id', 7781));
});

it('denies access without the permission', function () {
    $user = grantFinanceOverview([]);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")->assertForbidden();
});

it('hides a cross-campus student as not found', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->otherCampus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")->assertNotFound();
});

it('allows cross-campus view with the all-campus permission', function () {
    $user = grantFinanceOverview(['view_finance_student_overview', 'view_finance_all_campus']);
    $student = makeOverviewStudent($this->otherCampus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page->component('Finance/Student360/Show'));
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360/StudentOverviewShellTest.php`
Expected: FAIL — route `/finance/students/{student}` returns 404 / no `Finance/Student360/Show` component.

> If `Student::factory()->forCampus()` / `forProgram()` states differ from the audit test, copy the exact factory usage from `tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php:32-35` (it is the proven pattern in this suite).

- [ ] **Step 3: Add the FormRequest**

Create `app/Modules/Finance/Http/Requests/Student360ShowRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Student360ShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level `can:view_finance_student_overview` gate handles authorization.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'focus' => ['nullable', 'string', 'regex:/^(dng|invoice|charge|payment|installment):\d+$/'],
        ];
    }

    /**
     * Parse the optional focus deep-link target.
     *
     * @return array{type:string,id:int}|null
     */
    public function focusTarget(): ?array
    {
        $focus = (string) $this->query('focus', '');
        if (preg_match('/^(dng|invoice|charge|payment|installment):(\d+)$/', $focus, $m) !== 1) {
            return null;
        }

        return ['type' => $m[1], 'id' => (int) $m[2]];
    }
}
```

- [ ] **Step 4: Add the controller**

Create `app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Modules\Finance\Http\Requests\Student360ShowRequest;
use App\Modules\Finance\Queries\Audit\GetFinanceAuditGraphQuery;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Modules\Finance\Support\Audit\FinanceLedgerTimelineBuilder;
use App\Modules\Finance\Support\LifecycleDueExceptionReasonResolver;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only Student 360 shell. Identity + four derived balances + a deferred
 * signed ledger timeline. Reuses existing Finance read models — no money math
 * here. Campus-scoped: a student outside the current campus is 404 unless the
 * user holds `view_finance_all_campus`.
 */
class FinanceStudentOverviewController extends Controller
{
    public function show(
        Student $student,
        Student360ShowRequest $request,
        GetStudentBalanceQuery $balanceQuery,
        GetFinanceAuditGraphQuery $graphQuery,
        FinanceLedgerTimelineBuilder $timelineBuilder,
    ): Response {
        if (! $this->visible($student, $request)) {
            abort(404);
        }

        $studentId = (int) $student->id;
        $balance = $balanceQuery->handle($studentId); // student-level truth, all semesters
        $reason = LifecycleDueExceptionReasonResolver::resolve($student);

        $props = [
            'student' => [
                'id' => $studentId,
                'student_code' => $student->student_id,
                'full_name' => $student->full_name,
                'status' => $student->status,
                'academic_status' => $student->academic_status,
                'lifecycle_reason' => $reason->value,
                'lifecycle_label' => $reason->label(),
            ],
            'balances' => [
                'net_charges' => $balance['net_charges'],
                'total_paid' => $balance['total_paid'],
                'balance' => $balance['balance'],
                'unapplied_credit' => $balance['unapplied_credit'],
                'status' => $balance['status'],
            ],
            'focus' => $request->focusTarget(),
            'links' => [
                'audit' => route('finance.audit.index', ['target_type' => 'student', 'target_id' => $studentId]),
            ],
        ];

        // Basic ledger is heavy → deferred. Reuses the audit money-graph + signed
        // timeline builder so the 360 ledger and Audit Workspace never diverge.
        $props['ledger'] = Inertia::defer(function () use ($studentId, $graphQuery, $timelineBuilder): array {
            $graph = $graphQuery->handle(['type' => 'student', 'id' => $studentId]);

            return $timelineBuilder->build($graph);
        });

        return Inertia::render('Finance/Student360/Show', $props);
    }

    private function visible(Student $student, Student360ShowRequest $request): bool
    {
        $campusId = $this->currentCampusId();
        if ($campusId !== null && (int) $student->campus_id === $campusId) {
            return true;
        }

        return $request->user()?->can('view_finance_all_campus') ?? false;
    }

    private function currentCampusId(): ?int
    {
        if (! app()->bound('campus')) {
            return null;
        }

        $campus = app('campus');

        return $campus?->id !== null ? (int) $campus->id : null;
    }
}
```

- [ ] **Step 5: Register the route**

In `app/Modules/Finance/routes/web.php`, add the import near the other `Http\Web\Admin` imports at the top:

```php
use App\Modules\Finance\Http\Web\Admin\FinanceStudentOverviewController;
```

Inside the `Route::middleware(['auth', 'web'])->prefix('finance')->name('finance.')->group(...)` body, add (place it **above** the existing `students/{student}/charges` route so both resolve cleanly — Laravel matches full paths, but keep related routes together):

```php
    // Student 360 read-only shell (search destination)
    Route::get('/students/{student}', [FinanceStudentOverviewController::class, 'show'])
        ->middleware('can:view_finance_student_overview')
        ->name('students.overview');
```

- [ ] **Step 6: Create the TS types**

Create `resources/js/types/finance.ts`:

```typescript
export interface SemesterOption {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
}

export interface SemesterContext {
    selected_id: number | null;
    options: SemesterOption[];
}

export interface Student360Identity {
    id: number;
    student_code: string;
    full_name: string;
    status: string;
    academic_status: string | null;
    lifecycle_reason: string;
    lifecycle_label: string;
}

export interface Student360Balances {
    net_charges: number;
    total_paid: number;
    balance: number;
    unapplied_credit: number;
    status: string;
}

export interface LedgerEvent {
    at: string | null;
    type: string;
    signed_amount: number;
    label: string;
    refs: Record<string, number>;
}

export interface FinanceSearchResult {
    type: string;
    id: number;
    label: string;
    sublabel: string;
    url: string;
}
```

- [ ] **Step 7: Create the Inertia page**

Create `resources/js/pages/Finance/Student360/Show.vue`. Follows the audit page conventions (props are **snake_case**; `Deferred` uses a **string** `data` prop; `formatCurrency`/`formatDate` from `@/utils/format`):

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { LedgerEvent, Student360Balances, Student360Identity } from '@/types/finance';
import { formatCurrency, formatDate } from '@/utils/format';
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';

const props = defineProps<{
    student: Student360Identity;
    balances: Student360Balances;
    focus: { type: string; id: number } | null;
    links: { audit: string };
    ledger?: LedgerEvent[];
}>();

const balanceCards = [
    { key: 'net_charges', label: 'Phải thu', tone: 'text-foreground' },
    { key: 'total_paid', label: 'Đã thu', tone: 'text-green-700 dark:text-green-300' },
    { key: 'balance', label: 'Còn nợ', tone: 'text-orange-700 dark:text-orange-300' },
    { key: 'unapplied_credit', label: 'Dư chưa khớp', tone: 'text-blue-700 dark:text-blue-300' },
] as const;
</script>

<template>
    <Head :title="`Finance · ${props.student.full_name}`" />

    <div class="space-y-4">
        <!-- Header: dual status chip + identity + audit deep-link -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ props.student.full_name }}</h1>
                <p class="text-muted-foreground text-sm tabular-nums">{{ props.student.student_code }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Badge variant="secondary">{{ props.student.academic_status ?? props.student.status }}</Badge>
                    <Badge variant="outline">{{ props.student.lifecycle_label }}</Badge>
                </div>
            </div>
            <Button as-child variant="outline" size="sm">
                <Link :href="props.links.audit">
                    <ExternalLink class="mr-1 size-4" /> Mở Audit Workspace
                </Link>
            </Button>
        </div>

        <!-- Four core balances from SettlementService (via GetStudentBalanceQuery) -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <Card v-for="card in balanceCards" :key="card.key">
                <CardHeader class="pb-1">
                    <CardTitle class="text-muted-foreground text-xs font-medium">{{ card.label }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-lg font-semibold tabular-nums" :class="card.tone">
                        {{ formatCurrency(props.balances[card.key]) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Basic signed ledger (deferred) -->
        <Card>
            <CardHeader>
                <CardTitle class="text-sm">Sổ cái (dòng thời gian)</CardTitle>
            </CardHeader>
            <CardContent>
                <Deferred data="ledger">
                    <template #fallback>
                        <div class="space-y-2">
                            <Skeleton class="h-8 w-full" v-for="n in 4" :key="n" />
                        </div>
                    </template>

                    <div v-if="props.ledger && props.ledger.length > 0" class="divide-y">
                        <div v-for="(event, idx) in props.ledger" :key="idx" class="flex items-center justify-between py-2 text-sm">
                            <div>
                                <span class="font-medium">{{ event.label }}</span>
                                <span class="text-muted-foreground ml-2 text-xs">{{ event.at ? formatDate(event.at) : '—' }}</span>
                            </div>
                            <span
                                class="tabular-nums"
                                :class="event.signed_amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-foreground'"
                            >
                                {{ formatCurrency(event.signed_amount) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground py-6 text-center text-sm">Chưa có hoạt động sổ cái.</p>
                </Deferred>
            </CardContent>
        </Card>
    </div>
</template>
```

> Inertia v3 page-layout: most pages in this repo render inside `AppLayout`. Confirm how sibling Finance pages attach the layout (grep `resources/js/pages/Finance/Audit/Workspace.vue` for a `defineOptions({ layout })` or a persistent-layout import; the audit page is the canonical example). Match that page exactly so the 360 renders inside the same shell.

- [ ] **Step 8: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360/StudentOverviewShellTest.php`
Expected: PASS (5 passing).

- [ ] **Step 9: Lint the frontend files**

Run: `./scripts/dev.sh npm run lint -- resources/js/pages/Finance/Student360/Show.vue resources/js/types/finance.ts`
Expected: no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php \
  app/Modules/Finance/Http/Requests/Student360ShowRequest.php \
  app/Modules/Finance/routes/web.php \
  resources/js/pages/Finance/Student360/Show.vue \
  resources/js/types/finance.ts \
  tests/Feature/Finance/Student360/StudentOverviewShellTest.php
git commit -m "feat(finance): add minimal Student 360 route shell"
```

---

## Task 4: Global finance search endpoint + ⌘K command palette

The global "cửa vào số 1" (entry #1). A JSON endpoint reuses `ResolveFinanceAuditSearchQuery` (no new resolver logic) and maps each resolved target to a Student 360 deep-link (`focus=<type>:<id>` for non-student targets). A ⌘K palette in the topbar consumes it via `useApi` (JSON, non-navigating per `docs/rules/frontend.md` §7) and navigates on select.

**Files:**
- Create: `app/Modules/Finance/Http/Web/Admin/FinanceGlobalSearchController.php`
- Create: `resources/js/components/finance/FinanceCommandPalette.vue`
- Modify: `app/Modules/Finance/routes/web.php`
- Test: `tests/Feature/Finance/Search/FinanceGlobalSearchTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Search/FinanceGlobalSearchTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantFinanceSearch')) {
    function grantFinanceSearch(array $codes): User
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
    $this->semester = Semester::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

it('resolves a student code to a 360 deep link', function () {
    $user = grantFinanceSearch(['view_finance_student_overview']);
    $student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id, 'student_id' => 'SWB12345'])
        ->create();

    actingAs($user)->getJson('/finance/search?q=SWB12345')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'single')
        ->assertJsonPath('data.results.0.type', 'student')
        ->assertJsonPath('data.results.0.id', $student->id);
});

it('maps an invoice number to the owning student 360 with a focus link', function () {
    $user = grantFinanceSearch(['view_finance_student_overview']);
    $student = Student::factory()->forCampus($this->campus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id])
        ->create();
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-SEARCH-1',
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'subtotal' => 0, 'discount_total' => 0, 'total_amount' => 0, 'paid_amount' => 0,
    ]);

    actingAs($user)->getJson('/finance/search?q=INV-SEARCH-1')
        ->assertOk()
        ->assertJsonPath('data.status', 'single')
        ->assertJsonPath('data.results.0.type', 'invoice')
        ->assertJsonPath('data.results.0.id', $student->id); // result id is the owning student
});

it('returns empty for a cross-campus identifier', function () {
    $user = grantFinanceSearch(['view_finance_student_overview']);
    Student::factory()->forCampus($this->otherCampus)->forProgram($this->program)
        ->state(['intake' => 1, 'intake_semester_id' => $this->semester->id, 'student_id' => 'OTHER999'])
        ->create();

    actingAs($user)->getJson('/finance/search?q=OTHER999')
        ->assertOk()
        ->assertJsonPath('data.status', 'empty')
        ->assertJsonPath('data.results', []);
});

it('denies search without the permission', function () {
    $user = grantFinanceSearch([]);
    actingAs($user)->getJson('/finance/search?q=anything')->assertForbidden();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Search/FinanceGlobalSearchTest.php`
Expected: FAIL — `/finance/search` route does not exist (404/405).

- [ ] **Step 3: Add the controller**

Create `app/Modules/Finance/Http/Web/Admin/FinanceGlobalSearchController.php`. It reuses `ResolveFinanceAuditSearchQuery` and maps targets to owning-student 360 URLs (owner resolution mirrors `FinanceAuditWorkspaceController::ownerStudentId`):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Audit\ResolveFinanceAuditSearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON global finance search for the ⌘K palette. Reuses the audit resolver
 * (campus-scoped, deterministic precedence) and maps every resolved target to a
 * Student 360 deep link. Non-student targets carry a `focus=<type>:<id>` param.
 */
class FinanceGlobalSearchController extends Controller
{
    public function search(Request $request, ResolveFinanceAuditSearchQuery $resolver): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $resolution = $resolver->handle($q, $this->currentCampusId());

        return ApiResponse::success([
            'status' => $resolution['status'],
            'results' => $this->toResults($resolution),
        ]);
    }

    /**
     * @param  array{status:string,target:array{type:string,id:int}|null,matches:list<array{type:string,id:int,label:string,sublabel:string}>}  $resolution
     * @return list<array{type:string,id:int,label:string,sublabel:string,url:string}>
     */
    private function toResults(array $resolution): array
    {
        if ($resolution['status'] === 'single' && $resolution['target'] !== null) {
            $result = $this->targetToResult($resolution['target']);

            return $result !== null ? [$result] : [];
        }

        // Ambiguous student matches → already student-typed, map straight through.
        return array_values(array_filter(array_map(
            fn (array $match) => $this->targetToResult($match, $match['label'] ?? null, $match['sublabel'] ?? null),
            $resolution['matches'],
        )));
    }

    /**
     * @param  array{type:string,id:int}  $target
     * @return array{type:string,id:int,label:string,sublabel:string,url:string}|null
     */
    private function targetToResult(array $target, ?string $label = null, ?string $sublabel = null): ?array
    {
        $studentId = $this->ownerStudentId($target);
        if ($studentId === null) {
            return null;
        }

        $type = (string) $target['type'];
        $id = (int) $target['id'];
        $focus = $type !== 'student' ? "{$type}:{$id}" : null;

        $student = Student::find($studentId);

        return [
            'type' => $type,
            'id' => $studentId, // navigate to the owning student
            'label' => $label ?? (string) ($student?->full_name ?? ''),
            'sublabel' => $sublabel ?? (string) ($student?->student_id ?? ''),
            'url' => route('finance.students.overview', $focus
                ? ['student' => $studentId, 'focus' => $focus]
                : ['student' => $studentId]),
        ];
    }

    private function ownerStudentId(array $target): ?int
    {
        $type = (string) ($target['type'] ?? '');
        $id = (int) ($target['id'] ?? 0);

        $studentId = match ($type) {
            'student' => $id,
            'invoice' => StudentInvoice::find($id)?->student_id,
            'payment' => Payment::find($id)?->student_id,
            'charge' => FinanceCharge::find($id)?->student_id,
            'dng' => DngPaymentRequest::find($id)?->student_id,
            default => null,
        };

        return $studentId !== null ? (int) $studentId : null;
    }

    private function currentCampusId(): ?int
    {
        if (! app()->bound('campus')) {
            return null;
        }

        $campus = app('campus');

        return $campus?->id !== null ? (int) $campus->id : null;
    }
}
```

- [ ] **Step 4: Register the route**

In `app/Modules/Finance/routes/web.php`, add the import:

```php
use App\Modules\Finance\Http\Web\Admin\FinanceGlobalSearchController;
```

Inside the finance group, add:

```php
    // Global finance search (JSON) for the ⌘K palette
    Route::get('/search', [FinanceGlobalSearchController::class, 'search'])
        ->middleware('can:view_finance_student_overview')
        ->name('search');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Search/FinanceGlobalSearchTest.php`
Expected: PASS (4 passing).

- [ ] **Step 6: Build the ⌘K palette**

Create `resources/js/components/finance/FinanceCommandPalette.vue`. Uses `useApi().get` (JSON), debounced; ⌘K / Ctrl+K toggles; navigates with `router.visit` on select. Reuse the shadcn `command` + `dialog` primitives if present (grep `resources/js/components/ui/command`); otherwise use a `Dialog` + `Input` + list as below:

```vue
<script setup lang="ts">
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useApi } from '@/composables/useApiRequest';
import { financeRoutes } from '@/utils/routes';
import type { FinanceSearchResult } from '@/types/finance';
import { router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const open = ref(false);
const query = ref('');
const results = ref<FinanceSearchResult[]>([]);
const loading = ref(false);
const { get } = useApi();

let abort: AbortController | null = null;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const runSearch = async (term: string): Promise<void> => {
    abort?.abort();
    if (term.trim().length < 2) {
        results.value = [];
        return;
    }
    abort = new AbortController();
    loading.value = true;
    try {
        const res = await get<{ status: string; results: FinanceSearchResult[] }>(
            financeRoutes.search(),
            { q: term },
            { signal: abort.signal },
        );
        results.value = res.success && res.data ? res.data.results : [];
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
};

watch(query, (term) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => void runSearch(term), 300);
});

const select = (result: FinanceSearchResult): void => {
    open.value = false;
    query.value = '';
    results.value = [];
    router.visit(result.url);
};

const onKeydown = (e: KeyboardEvent): void => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        open.value = !open.value;
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <button
        type="button"
        class="text-muted-foreground hover:bg-accent inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
        @click="open = true"
    >
        <Search class="size-4" />
        <span class="hidden md:inline">Tìm sinh viên…</span>
        <kbd class="bg-muted ml-2 hidden rounded px-1.5 text-xs md:inline">⌘K</kbd>
    </button>

    <Dialog v-model:open="open">
        <DialogContent class="max-w-xl p-0">
            <div class="border-b p-2">
                <Input v-model="query" placeholder="Mã SV · tên · số invoice · item_id DNG" autofocus class="border-0 focus-visible:ring-0" />
            </div>
            <ul class="max-h-80 overflow-y-auto p-1">
                <li v-if="loading" class="text-muted-foreground px-3 py-2 text-sm">Đang tìm…</li>
                <li v-else-if="results.length === 0 && query.length >= 2" class="text-muted-foreground px-3 py-2 text-sm">Không có kết quả.</li>
                <li
                    v-for="result in results"
                    :key="`${result.type}-${result.id}`"
                    class="hover:bg-accent flex cursor-pointer items-center justify-between rounded-md px-3 py-2 text-sm"
                    @click="select(result)"
                >
                    <span class="font-medium">{{ result.label }}</span>
                    <span class="text-muted-foreground tabular-nums text-xs">{{ result.sublabel }}</span>
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
```

> If `@/components/ui/dialog` exports differ (e.g. requires `DialogTitle` for a11y), copy the import/usage from an existing modal page (grep `from '@/components/ui/dialog'`). Keep behavior identical; only match the local API.

- [ ] **Step 7: Lint the new component**

Run: `./scripts/dev.sh npm run lint -- resources/js/components/finance/FinanceCommandPalette.vue`
Expected: no errors. (Mounting into the topbar happens in Task 7.)

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Finance/Http/Web/Admin/FinanceGlobalSearchController.php \
  app/Modules/Finance/routes/web.php \
  resources/js/components/finance/FinanceCommandPalette.vue \
  tests/Feature/Finance/Search/FinanceGlobalSearchTest.php
git commit -m "feat(finance): add global search endpoint and command palette"
```

---

## Task 5: Semester topbar contract (single source)

Per design §3.1: the selected semester becomes one source — a session value surfaced as a shared Inertia prop — instead of every page keeping its own semester filter. Adds the `obeys_semester` convention (documented; consumed by semester-bound surfaces in M3/M4). The Student 360 header is `obeys_semester: false` (student money is all-semester truth), so M1 ships the plumbing without a semester-bound consumer yet.

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (shared `semester` prop)
- Create: `app/Modules/Finance/Http/Web/Admin/FinanceSemesterContextController.php`
- Modify: `app/Modules/Finance/routes/web.php`
- Create: `resources/js/components/finance/SemesterSwitcher.vue`
- Modify: `resources/js/types/index.ts` (extend `SharedData`/`PageProps` with `semester?: SemesterContext`)
- Test: `tests/Feature/Finance/Shell/SemesterContextTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Shell/SemesterContextTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

if (! function_exists('grantFinanceShell')) {
    function grantFinanceShell(array $codes): User
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
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

it('shares a semester prop with options and selection to finance users', function () {
    $active = Semester::factory()->create(['is_active' => true]);
    Semester::factory()->create(['is_active' => false]);
    $user = grantFinanceShell(['view_finance_student_overview']);

    actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('semester.selected_id', $active->id)
            ->has('semester.options'));
});

it('omits the semester prop for users without finance permissions', function () {
    Semester::factory()->create(['is_active' => true]);
    $user = grantFinanceShell([]);

    actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('semester', null));
});

it('persists a selected semester into the session', function () {
    $other = Semester::factory()->create(['is_active' => false]);
    $user = grantFinanceShell(['view_finance_student_overview']);

    actingAs($user)->post('/finance/semester-context', ['semester_id' => $other->id])
        ->assertRedirect();

    expect(session('current_semester_id'))->toBe($other->id);
});
```

> If `/dashboard` is not reachable in the test env, substitute any always-authorized Inertia GET route (e.g. the finance audit index with `view_finance_audit_workspace` granted). The assertion targets the shared prop, which is route-independent.

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Shell/SemesterContextTest.php`
Expected: FAIL — no `semester` shared prop; `/finance/semester-context` route missing.

- [ ] **Step 3: Add the shared prop**

In `app/Http/Middleware/HandleInertiaRequests.php`, add the needed imports at the top:

```php
use App\Models\Semester;
use App\Services\PermissionService;
```

(`PermissionService` is likely already imported — it is used by the `auth` closure. Do not duplicate.) Inside the array returned by `share()`, add a top-level `semester` key (sibling of `auth`):

```php
            'semester' => function () use ($user, $currentCampusId) {
                if (! $user) {
                    return null;
                }

                $permissions = app(PermissionService::class)->getUserPermissions($user, $currentCampusId);
                $financePerms = ['view_finance_student_overview', 'view_finance_audit_workspace', 'view_finance_operations_dashboard'];
                if (count(array_intersect($financePerms, $permissions)) === 0) {
                    return null; // bound cost: only finance users pay for the semester query
                }

                $selectedId = session('current_semester_id');
                if ($selectedId === null) {
                    $selectedId = Semester::query()->where('is_active', true)->value('id');
                }

                return [
                    'selected_id' => $selectedId !== null ? (int) $selectedId : null,
                    'options' => Semester::query()
                        ->where('is_archived', false)
                        ->orderByDesc('start_date')
                        ->get(['id', 'code', 'name', 'is_active'])
                        ->map(fn (Semester $s) => [
                            'id' => (int) $s->id,
                            'code' => $s->code,
                            'name' => $s->name,
                            'is_active' => (bool) $s->is_active,
                        ])
                        ->all(),
                ];
            },
```

- [ ] **Step 4: Add the set-context controller**

Create `app/Modules/Finance/Http/Web/Admin/FinanceSemesterContextController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sets the operator's selected semester (single source for semester-bound finance
 * surfaces). Stores only in session; reflected app-wide via the shared `semester`
 * Inertia prop. No money state.
 */
class FinanceSemesterContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ]);

        session(['current_semester_id' => $validated['semester_id'] ?? null]);

        return back();
    }
}
```

- [ ] **Step 5: Register the route**

In `app/Modules/Finance/routes/web.php`, add the import and route:

```php
use App\Modules\Finance\Http\Web\Admin\FinanceSemesterContextController;
```

```php
    // Operator semester selection (single source; reflected via shared prop)
    Route::post('/semester-context', [FinanceSemesterContextController::class, 'update'])
        ->name('semester-context.update');
```

(No `can:` gate — it only writes the user's own session and is harmless; the switcher UI is permission-gated at render in Task 7.)

- [ ] **Step 6: Extend the shared-data TS type**

In `resources/js/types/index.ts` (the file that defines `SharedData` / `PageProps`; grep for `interface SharedData` or `current_campus_id` to find it), add the import and the optional field:

```typescript
import type { SemesterContext } from '@/types/finance';
```

Add to the `SharedData` (and/or `PageProps`) interface:

```typescript
    semester?: SemesterContext | null;
```

- [ ] **Step 7: Build the SemesterSwitcher**

Create `resources/js/components/finance/SemesterSwitcher.vue`. Reads the shared prop, POSTs the selection via `useForm` (Inertia navigation — matches the `docs/rules/frontend.md` §5 "Inertia form" case), then the reload re-resolves the shared prop:

```vue
<script setup lang="ts">
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { SharedData } from '@/types';
import { financeRoutes } from '@/utils/routes';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage<SharedData>();
const semester = computed(() => page.props.semester ?? null);

const selected = computed<string>({
    get: () => (semester.value?.selected_id != null ? String(semester.value.selected_id) : 'all'),
    set: (value: string) => {
        router.post(
            financeRoutes.semesterContext.update(),
            { semester_id: value === 'all' ? null : Number(value) },
            { preserveScroll: true, preserveState: false },
        );
    },
});
</script>

<template>
    <Select v-if="semester" v-model="selected">
        <SelectTrigger class="h-8 w-44 text-sm">
            <SelectValue placeholder="Kỳ học" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem value="all">Tất cả kỳ</SelectItem>
            <SelectItem v-for="option in semester.options" :key="option.id" :value="String(option.id)">
                {{ option.name }}{{ option.is_active ? ' ·  hiện tại' : '' }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
```

> Shadcn `Select` requires non-empty string values (`docs/rules/frontend.md` §3): `"all"` is the reset sentinel, numeric ids are `String(id)`. Do not use `value=""`.

- [ ] **Step 8: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Shell/SemesterContextTest.php`
Expected: PASS (3 passing).

- [ ] **Step 9: Lint the changed frontend files**

Run: `./scripts/dev.sh npm run lint -- resources/js/components/finance/SemesterSwitcher.vue resources/js/types/index.ts`
Expected: no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php \
  app/Modules/Finance/Http/Web/Admin/FinanceSemesterContextController.php \
  app/Modules/Finance/routes/web.php \
  resources/js/components/finance/SemesterSwitcher.vue \
  resources/js/types/index.ts \
  tests/Feature/Finance/Shell/SemesterContextTest.php
git commit -m "feat(finance): add semester context shared prop and switcher"
```

---

## Task 6: IA — restructure the Finance Office sidebar into 5 work-groups

Per design §3.2: replace the object-typed Finance menu with 5 work-organized groups, using `financeRoutes` (no literal URLs). This is a pure menu-config rewrite; permission gating per item is preserved exactly. **Student 360 is intentionally NOT a sidebar item** (reached via ⌘K + row clicks). "Hôm nay" points at the existing Billing Dashboard until the Cockpit replaces it in Milestone 3.

### Menu migration table (current item → new group → action)

| Current item (menu-sidebar.ts) | Permission | New group | Action |
|---|---|---|---|
| Audit Workspace `/finance/audit` | `view_finance_audit_workspace` | 🔎 Tra cứu & Audit | move → `financeRoutes.audit()` |
| Billing Dashboard `/finance/operations/dashboard` | `view_finance_operations_dashboard` | ⌂ Hôm nay | move → `financeRoutes.today.dashboard()` (Cockpit replaces in M3) |
| Generate HP (Tuition) `/finance/major/charges` | `create_finance_charges` | ＄ Sinh phí | move → `financeRoutes.feeGeneration.majorCharges()` |
| Batch Charges (All Students) `/finance/operations/generate-charges` | `view_finance_operations_generate_charges` | ＄ Sinh phí | move → `financeRoutes.feeGeneration.batchCharges()` |
| EGC · Generate Charges `/finance/egc/charges` | `generate_egc_finance_charges` | ＄ Sinh phí | move → `financeRoutes.feeGeneration.egcCharges()` |
| EGC · Block Results `/finance/egc/block-results` | `view_egc_block_results` | ＄ Sinh phí | move → `financeRoutes.feeGeneration.egcBlockResults()` |
| EGC · Retake Adjustments `/finance/egc/retake-adjustments` | `view_egc_retake_adjustments` | ＄ Sinh phí | move → `financeRoutes.feeGeneration.egcRetakeAdjustments()` |
| EGC · Carry Forward `/finance/egc/carry-forward` | `view_egc_retake_adjustments` | ＄ Sinh phí | move → `financeRoutes.feeGeneration.egcCarryForward()` |
| DNG Worklist `/finance/operations/dng-worklist` | `create_finance_payments` | 💳 Thu & Đối soát | move → `financeRoutes.collect.dngWorklist()` |
| DNG Payment Requests `/finance/dng/payment-requests` | `view_finance_dng_payment_requests` | 💳 Thu & Đối soát | move → `financeRoutes.collect.dngPaymentRequests()` |
| DNG Webhook Events `/finance/dng/webhook-events` | `view_finance_dng_webhook_events` | 💳 Thu & Đối soát | move → `financeRoutes.collect.dngWebhookEvents()` |
| Settlement Worklist `/finance/operations/settlement` | `allocate_finance_payment` | 💳 Thu & Đối soát | move → `financeRoutes.collect.settlement()` |
| Payments `/finance/payments` | `view_finance_payments` | 💳 Thu & Đối soát | move → `financeRoutes.collect.payments()` |
| DNG Due Reminders `/finance/operations/due-calendar` (under Finance Operations) | `view_finance_operations_due_calendar` | 💳 Thu & Đối soát | move → `financeRoutes.collect.dueReminders()` |
| DNG Due Reminders `/finance/operations/due-calendar` (DUPLICATE under EGC Finance) | `view_finance_operations_due_calendar` | — | **remove (dedupe)** — unified into the Thu & Đối soát entry above |
| Exceptions Queue `/finance/operations/exceptions` | `view_finance_operations_exceptions` | 🛟 Ngoại lệ | move → `financeRoutes.exceptions.queue()` |
| Lifecycle Exceptions `/finance/operations/lifecycle-exceptions` | `view_finance_operations_due_calendar` | 🛟 Ngoại lệ | move → `financeRoutes.exceptions.lifecycle()` |
| Lifecycle History `/finance/operations/lifecycle-exception-history` | `view_finance_operations_due_calendar` | 🛟 Ngoại lệ | move → `financeRoutes.exceptions.lifecycleHistory()` |
| Charge Ledger (Global) `/finance/charges` | `view_finance_charges` | 🔎 Tra cứu & Audit | move → `financeRoutes.lookup.chargeLedger()` |
| Invoices `/finance/invoices` | `view_finance_invoices` | 🔎 Tra cứu & Audit | move → `financeRoutes.lookup.invoices()` |
| Discounts & Funding (group: Tuition Plans, Scholarships, Student Scholarships, Vouchers) | various | **Discounts & Funding (kept as separate group)** | **keep unchanged** — these live outside the Finance module (`/tuition-plans`, `/scholarships`, …); per §3.2 do NOT fold into Sinh phí. Leave literal hrefs (out of finance route-helper scope). |
| Student 360 | `view_finance_student_overview` | — | **not in sidebar** — reached via ⌘K + row clicks (design §3.2) |

**Files:**
- Modify: `resources/js/constants/menu-sidebar.ts` (replace the `label: 'Finance Office'` group; keep `Discounts & Funding` as its own group)

- [ ] **Step 1: Replace the Finance Office group**

In `resources/js/constants/menu-sidebar.ts`, add `financeRoutes` to the import from `@/utils/routes` (line 51 currently imports `academicSummaryRoutes, attendanceRoutes, …, systemRoutes`):

```typescript
import { academicSummaryRoutes, attendanceRoutes, courseRoutes, curriculumRoutes, financeRoutes, lecturerRoutes, studentRoutes, systemRoutes } from '@/utils/routes';
```

Ensure these icons are present in the `lucide-vue-next` import block (add any missing): `LayoutDashboard, Play, Layers, GraduationCap, CheckSquare, TrendingUp, ArrowRightLeft, Send, Receipt, CalendarIcon, AlertCircle, FileWarning, Clock, Sparkles, BarChart3, Search, Award, Calculator, Ticket, Users, DollarSign`. (Most already exist.)

Replace the entire `{ label: 'Finance Office', items: [ … ] }` object (current lines ~314–490) with two groups — the 5 work-groups, then the unchanged Discounts & Funding:

```typescript
    {
        label: 'Finance Office',
        items: [
            {
                title: 'Hôm nay',
                href: financeRoutes.today.dashboard(),
                icon: LayoutDashboard,
                requiredPermissions: ['view_finance_operations_dashboard'],
            },
            {
                title: 'Sinh phí',
                href: '#',
                icon: DollarSign,
                children: [
                    { title: 'Generate HP (Tuition)', href: financeRoutes.feeGeneration.majorCharges(), icon: Play, requiredPermissions: ['create_finance_charges'] },
                    { title: 'Batch Charges (All Students)', href: financeRoutes.feeGeneration.batchCharges(), icon: Layers, requiredPermissions: ['view_finance_operations_generate_charges'] },
                    { title: 'EGC · Generate Charges', href: financeRoutes.feeGeneration.egcCharges(), icon: GraduationCap, requiredPermissions: ['generate_egc_finance_charges'] },
                    { title: 'EGC · Block Results', href: financeRoutes.feeGeneration.egcBlockResults(), icon: CheckSquare, requiredPermissions: ['view_egc_block_results'] },
                    { title: 'EGC · Retake Adjustments', href: financeRoutes.feeGeneration.egcRetakeAdjustments(), icon: TrendingUp, requiredPermissions: ['view_egc_retake_adjustments'] },
                    { title: 'EGC · Carry Forward', href: financeRoutes.feeGeneration.egcCarryForward(), icon: ArrowRightLeft, requiredPermissions: ['view_egc_retake_adjustments'] },
                ],
            },
            {
                title: 'Thu & Đối soát',
                href: '#',
                icon: Send,
                children: [
                    { title: 'DNG Worklist', href: financeRoutes.collect.dngWorklist(), icon: Send, requiredPermissions: ['create_finance_payments'] },
                    { title: 'DNG Payment Requests', href: financeRoutes.collect.dngPaymentRequests(), icon: Receipt, requiredPermissions: ['view_finance_dng_payment_requests'] },
                    { title: 'DNG Webhook Events', href: financeRoutes.collect.dngWebhookEvents(), icon: Receipt, requiredPermissions: ['view_finance_dng_webhook_events'] },
                    { title: 'Settlement Worklist', href: financeRoutes.collect.settlement(), icon: Sparkles, requiredPermissions: ['allocate_finance_payment'] },
                    { title: 'Payments', href: financeRoutes.collect.payments(), icon: BarChart3, requiredPermissions: ['view_finance_payments'] },
                    { title: 'DNG Due Reminders', href: financeRoutes.collect.dueReminders(), icon: CalendarIcon, requiredPermissions: ['view_finance_operations_due_calendar'] },
                ],
            },
            {
                title: 'Ngoại lệ',
                href: '#',
                icon: FileWarning,
                children: [
                    { title: 'Exceptions Queue', href: financeRoutes.exceptions.queue(), icon: AlertCircle, requiredPermissions: ['view_finance_operations_exceptions'] },
                    { title: 'Lifecycle Exceptions', href: financeRoutes.exceptions.lifecycle(), icon: FileWarning, requiredPermissions: ['view_finance_operations_due_calendar'] },
                    { title: 'Lifecycle History', href: financeRoutes.exceptions.lifecycleHistory(), icon: Clock, requiredPermissions: ['view_finance_operations_due_calendar'] },
                ],
            },
            {
                title: 'Tra cứu & Audit',
                href: '#',
                icon: Search,
                children: [
                    { title: 'Audit Workspace', href: financeRoutes.audit(), icon: Search, requiredPermissions: ['view_finance_audit_workspace'] },
                    { title: 'Charge Ledger (Global)', href: financeRoutes.lookup.chargeLedger(), icon: BarChart3, requiredPermissions: ['view_finance_charges'] },
                    { title: 'Invoices', href: financeRoutes.lookup.invoices(), icon: Receipt, requiredPermissions: ['view_finance_invoices'] },
                ],
            },
        ],
    },
    {
        label: 'Discounts & Funding',
        items: [
            {
                title: 'Discounts & Funding',
                href: '#',
                icon: Award,
                children: [
                    { title: 'Tuition Plans', href: '/tuition-plans', icon: Calculator, requiredPermissions: ['view_tuition_plan'] },
                    { title: 'Scholarships', href: '/scholarships', icon: Award, requiredPermissions: ['view_scholarship'] },
                    { title: 'Student Scholarships', href: '/student-scholarships', icon: Users, requiredPermissions: ['assign_scholarship'] },
                    { title: 'Vouchers', href: '/vouchers', icon: Ticket, requiredPermissions: ['view_voucher'] },
                ],
            },
        ],
    },
```

> `Discounts & Funding` keeps literal hrefs deliberately: those routes (`/tuition-plans`, `/scholarships`, …) live outside the Finance module and outside `financeRoutes`. Per design §3.2 this is an explicit "keep, do not merge into Sinh phí" decision, not an oversight.

- [ ] **Step 2: Lint the menu file**

Run: `./scripts/dev.sh npm run lint -- resources/js/constants/menu-sidebar.ts`
Expected: no errors (eslint may reorder the icon imports).

- [ ] **Step 3: Commit**

```bash
git add resources/js/constants/menu-sidebar.ts
git commit -m "feat(finance): restructure Finance Office sidebar into 5 work-groups"
```

---

## Task 7: Mount topbar affordances + integration smoke + harness evidence

Wires the palette and switcher into the always-visible topbar (permission-gated), runs the full finance suite, performs a browser smoke of the shell, and records the milestone in the Harness story.

**Files:**
- Modify: `resources/js/components/AppSidebarHeader.vue`
- Modify: `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/` (validation.md / new evidence)

- [ ] **Step 1: Mount palette + switcher in the topbar**

Edit `resources/js/components/AppSidebarHeader.vue`. Add imports and `usePermissions`, render the two affordances gated by finance permission. Replace the `<script setup>` and the header inner content:

```vue
<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import FinanceCommandPalette from '@/components/finance/FinanceCommandPalette.vue';
import SemesterSwitcher from '@/components/finance/SemesterSwitcher.vue';
import NotificationPopper from '@/components/NotificationPopper.vue';
import StudentSearchDropdown from '@/components/StudentSearchDropdown.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import type { BreadcrumbItemType } from '@/types';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { canAny } = usePermissions();
const showFinanceShell = computed(() =>
    canAny(['view_finance_student_overview', 'view_finance_audit_workspace', 'view_finance_operations_dashboard']),
);
</script>

<template>
    <header
        class="bg-background/80 supports-[backdrop-filter]:bg-background/60 border-sidebar-border/70 sticky top-0 z-10 flex h-16 shrink-0 items-center gap-2 border-b px-6 backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex w-full items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <SidebarTrigger class="-ml-1" />
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
                <StudentSearchDropdown />
            </div>
            <div class="flex items-center gap-2">
                <template v-if="showFinanceShell">
                    <FinanceCommandPalette />
                    <SemesterSwitcher />
                </template>
                <NotificationPopper />
            </div>
        </div>
    </header>
</template>
```

- [ ] **Step 2: Lint the topbar**

Run: `./scripts/dev.sh npm run lint -- resources/js/components/AppSidebarHeader.vue`
Expected: no errors.

- [ ] **Step 3: Run the full Milestone-1 finance test set**

Run: `./scripts/dev.sh test tests/Feature/Finance/Student360 tests/Feature/Finance/Search tests/Feature/Finance/Shell`
Expected: all PASS (12 tests across the three files). Also re-run the existing audit suite to confirm no regression: `./scripts/dev.sh test tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php` → PASS.

- [ ] **Step 4: Build assets + browser smoke**

Run: `./scripts/dev.sh npm run build` (confirms the Vue/TS compiles without the dev-container type-check OOM).
Then browser-smoke as a finance user:
1. Top bar shows the ⌘K button + semester switcher.
2. Press ⌘K → type a known student code → result appears → Enter/click → lands on `/finance/students/{id}` (360 shell renders identity + 4 balances; ledger skeleton resolves).
3. Type a known invoice number → result maps to the owning student's 360 with `?focus=invoice:<id>`.
4. Change the semester switcher → page reloads, selection persists (re-open switcher shows the new value).
5. Sidebar shows the 5 Finance work-groups + Discounts & Funding; no dead links; items hidden for permissions you lack.

Record the result (pass/fail per step) in the trace. If any step fails, fix before continuing — do not mark the milestone done on a red smoke.

- [ ] **Step 5: Record menu-migration + acceptance evidence in the story**

Append the menu-migration table (from Task 6) and the Milestone-1 acceptance evidence (test names + smoke results) to `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md` under a new "Milestone 1 — Shell + 360 foundation" section. Note **Portal impact: none** and that **no money state changed** (so no `finance:audit-invariants` run is required).

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/AppSidebarHeader.vue \
  docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md
git commit -m "feat(finance): mount global search and semester switcher in topbar"
```

- [ ] **Step 7: Record the Harness trace**

```bash
./scripts/harness trace --summary "S-010 Milestone 1: finance shell + Student 360 foundation" --story FIN-REV-010 --actions "route constants, permissions, 360 shell, global search + palette, semester contract, IA 5-group sidebar, topbar mount" --changed "app/Modules/Finance, app/Http/Middleware/HandleInertiaRequests.php, config/permission.php, resources/js (finance shell)" --outcome completed --friction "no JS unit runner; vue-tsc OOM in dev container — used eslint + browser smoke"
```

---

## Self-Review (run against the design spec)

**1. Spec coverage (design §10 Milestone 1 = "App shell + IA 5 nhóm + tìm SV toàn cục + 360 route shell tối thiểu"):**
- App shell topbar (always-visible) — Task 7 (mounts into `AppSidebarHeader`, the sticky topbar). ✅
- Global ⌘K student search resolving SV code / name / invoice / DNG `item_id` → 360 — Tasks 4 (endpoint reuses `ResolveFinanceAuditSearchQuery`, which already handles all four identifier types) + 7 (palette). ✅
- Semester selector + single-source contract (query/session + shared prop + `obeys_semester` convention) — Task 5. ✅ (`obeys_semester` shipped as a documented convention + plumbing; first semester-bound consumer is M3 — explicitly noted, not silently dropped.)
- Campus + User context — already present in shared `auth` prop; 360 + search are campus-scoped (Tasks 3–4). ✅
- IA 5-group sidebar + full migration table + `Discounts & Funding` decision + DNG-Due-Reminders dedupe — Task 6. ✅
- Finance route constants (`financeRoutes`) before IA — Task 1 (sequenced first). ✅
- Minimal Student 360 route shell (identity + 4 `SettlementService` balances via `GetStudentBalanceQuery` + basic ledger via `FinanceLedgerTimelineBuilder`) as a valid search destination — Task 3, with `focus=<type>:<id>` accepted now. ✅
- New permissions named per surface + seeded/synced + role-mapped (design §8) — Task 2. ✅
- Portal impact: none — stated in header + Task 7 evidence. ✅
- Scope guard (no money-write logic; read-model + thin adapters only) — every backend addition is read-only or session-only; no Action/Service/invariant touched. ✅

**2. Placeholder scan:** No "TBD/TODO/implement later"; every code step ships complete code; every command has expected output. Two honest verification caveats are called out explicitly (no JS unit runner; `vue-tsc` OOM in dev container) rather than prescribing tests that cannot run — this is accuracy, not a placeholder.

**3. Type/name consistency:** Route names match `app/Modules/Finance/routes/web.php` group `finance.` + the new `students.overview` / `search` / `semester-context.update`. `financeRoutes.students.overview(id, focus?)` ↔ controller param `{student}` + `Student360ShowRequest.focusTarget()` regex (`dng|invoice|charge|payment|installment`) ↔ search `focus="{type}:{id}"`. Balance keys (`net_charges/total_paid/balance/unapplied_credit/status`) match `GetStudentBalanceQuery::handle` return verbatim. Ledger row shape (`at/type/signed_amount/label/refs`) matches `FinanceLedgerTimelineBuilder::build`. Shared prop `semester.{selected_id,options[]}` ↔ `SemesterContext` TS type ↔ `SemesterSwitcher`. Permission strings match `config/permission.php` additions. Test setup mirrors `FinanceAuditWorkspacePageTest` exactly. ✅

**Two flagged assumptions for the implementer to confirm in-step (each has a fallback instruction):** (a) the exact persistent-layout attachment used by `Finance/Audit/Workspace.vue` (Task 3 Step 7); (b) the local `@/components/ui/dialog` + `@/components/ui/command` export surface (Task 4 Step 6). Both are "match the existing sibling" confirmations, not open design questions.

---

## Roadmap appendix — Milestones 2–5 (one plan each, written when started)

Per design §10, each is its own spec → plan. Sketches below are sequencing + key contracts only, not task-level detail.

### Milestone 2 — Student 360 (full)
- **Builds on:** the M1 360 route shell (same URL/destination — *augment, never change the URL*).
- **Adds:** 4 status cards (Số dư & phân bổ · DNG hiện tại · Trả góp · Ngoại lệ), the dual ledger/timeline lenses (Sổ cái grouped Kỳ→Invoice→line via money-graph + snapshot; Dòng thời gian via `FinanceLedgerTimelineBuilder`), the DNG 2-call state stepper, the "Thao tác ▾" action menu, and the `focus=<type>:<id>` scroll+highlight behavior (M1 only echoes it).
- **New backend (read + thin adapters):** per-student status-card aggregates (reuse `SettlementService`, DNG models, installment queries); manual-allocate **preview** read-model (design §5.3 — distinct from the existing auto-allocate JSON preview); action adapter + FormRequest for "Ghi nhận thanh toán" (the old `/finance/payments/create` route was removed — §5.2).
- **Pattern births (reused by M3+):** signed ledger, state stepper, 4-layer safe-destructive action (block → impact → reason → ack+audit, §7.2) with the **two audit sinks** (lifecycle review events vs DNG cancel payload/log) and the `void_finance_charges` gate gap closed for both the DNG adapter and the `lifecycle-exceptions.resolve` flow.
- **Key risk:** destructive-action permission matrix (§7.2) — render-by-permission, blocking reasons from backend; new adapters must add `void_finance_charges` when linked charges exist.

### Milestone 3 — Cockpit "Hôm nay"
- **Builds on:** M2 patterns (Action Panel uses the safe-destructive flow); replaces the "Hôm nay" sidebar link target.
- **Adds:** CRITICAL banner, KPI ribbon, "Cần xử lý" queues (Webhook lỗi · Tiền chờ phân bổ · DNG đến hạn · Lifecycle review · Sai sót sinh phí · Installment push failed), Action Panel slideover, "Sức khỏe dữ liệu" (15 invariants → drilldown), "Theo giai đoạn" adaptive shortcuts.
- **Cross-dependency (design §10 note):** the **invariant drilldown contract** (§4.4 — extend Audit route with `finding_code`/`scope`/`sample_id` *or* a read-model) + the **sample-type→audit-target mapping table** must land **inside this milestone's story**, not deferred to M5. "Số dư khớp" denominator = `SettlementService::snapshotDriftsFromCache` (both `cached_paid` and `cached_total`). Campus lock on `scope="toàn hệ thống"` = current campus unless `view_finance_all_campus`.
- **New permission:** `view_finance_cockpit` (do not fold into `view_finance_operations_dashboard`); each widget still checks its source permission.
- **Live data:** `usePoll` with explicit interval (~60–120s), tab-background backoff, manual refresh; count/aggregate queries only (no full-row pulls).

### Milestone 4 — Batch Studio
- **Wizard:** ① Thiết lập → ② Xem trước → ③ Xác nhận → ④ Kết quả, shared across Sinh phí / Đẩy DNG / Nhắc nợ.
- **Preview-safety contract (§6.3, mandatory):** preview token bound to `user_id` + scope params + **per-line content hash over the canonical resolved payload** (id/source/fee fingerprint=`config_id+updated_at` since `TuitionPlan` has no version column / discount ids / idempotency / warning codes), one-time, short TTL; commit sends token + the chosen subset; backend **recompute-and-compare per selected line** → any drift blocks commit.
- **Backend truth to honor (do not change):** Sinh phí = single-transaction envelope (per-SV catch & skip, partial success still commits — §6.5), safe on **sequential** rerun via check-then-create (no concurrency guarantee → disable overlapping submits); DNG push = per-student transaction, partial success, **rerun can cancel old DNG** (§6.4), override-mismatch ⇒ ad-hoc + drops installment linkage (§6.2); batch limit 100/req ⇒ UI chunks; Nhắc nợ = per-recipient, block double-send.
- **Result screens differ:** Sinh phí summary vs DNG/Nhắc nợ per-student partial-failure + retry-the-failed-subset.

### Milestone 5 — Tra cứu & Audit
- **Mostly reuse:** ledger/invoice lookups on existing patterns; Audit graph (S-009, already shipped) is the *destination* of the M3 invariant drilldown.
- **Legacy migration (§8):** any `response()->json()` finance endpoints a new surface consumes (e.g. auto-allocate preview `PaymentController:231`, DNG data `:74`) must migrate to `ApiResponse::success()`.
- **Lowest risk** of the five — schedule last.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-15-finance-office-shell-and-360-foundation.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints for review.

Which approach?
