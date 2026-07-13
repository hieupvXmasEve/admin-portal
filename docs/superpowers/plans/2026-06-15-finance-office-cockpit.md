# Finance Office — Cockpit "Hôm nay" — Implementation Plan (Milestone 3)

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Cockpit "Hôm nay" triage screen — a CRITICAL money-integrity banner, a compact KPI ribbon, the "Cần xử lý" priority queues with an in-place Action Panel, the "Sức khỏe dữ liệu" 15-invariant trust panel with one-click drilldown into the Audit Workspace, and adaptive "Theo giai đoạn" shortcuts — by reusing existing summary queries and the existing integrity registry. No money-write logic and no money math is added.

**Architecture:** Read-only aggregation. The Cockpit renders at `GET /finance/cockpit`. Live, light props (`kpi`, `queues`, `phase`) reuse existing summary queries and are refreshed by `usePoll` (background-throttled) + a manual refresh. The heavier `data_health` prop (15 invariant counts + "Số dư khớp" + the CRITICAL banner source) is **deferred** and manually refreshed, scoped to the current campus (or all-campus with permission). The **invariant drilldown contract** — the M3-owned cross-dependency from §10 — extends `FinanceAuditSearchRequest`/`FinanceAuditWorkspaceController` with `finding_code`/`scope`/`sample_id` plus a `FinanceInvariantSampleResolver` that maps an invariant sample to an existing audit target (or a list view for un-graphable samples). The Action Panel is a `Sheet` slideover that fetches top-N rows for a queue on demand and submits the queue's existing write action.

**Tech Stack:** Laravel 13, Inertia v3 (`usePoll`, `Inertia::defer`), Vue 3 `<script setup lang="ts">`, Tailwind v4, Ziggy, Pest, shadcn-vue (`@/components/ui/*` incl. `sheet`, `card`, `badge`, `button`), `StatsCard.vue`, `lucide-vue-next`.

**Depends on:** Milestone 1 (shell, `financeRoutes`, semester shared prop, `view_finance_student_overview`, `view_finance_all_campus`) and Milestone 2 (reused patterns: `Sheet` drawers, `DngStateStepper`, 4-layer destructive UI). M1 + M2 must be merged first. The Cockpit replaces the M1 "Hôm nay" sidebar target (currently the Billing Dashboard).

**Story:** `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/` (`FIN-REV-010`). Design source: `docs/features/finance/finance-office-ux-redesign-design.md` §4, §3.1, §8, §10 milestone 3. **Portal impact: none.**

**Risk lane:** normal-to-high (`authz`, `read-model`, campus-scope correctness). No money writes occur in the Cockpit itself; the Action Panel actions reuse already-tested write routes (webhook retry, acknowledge, allocate). Hard gates: campus lock on "toàn hệ thống" (= current campus unless `view_finance_all_campus`); per-widget source-permission checks; light queue counts (no full-row pulls in the polled path).

---

## Testing approach (same as M1/M2 — read first)

Backend = TDD with Pest (`./scripts/dev.sh test <path>`), using the proven pattern (`grant…()` mock of `PermissionService::getUserPermissions`, `session(['current_campus_id' => …])` + `app()->singleton('campus', fn () => $campus)`, then `assertInertia`/`assertJsonPath`). Frontend = `./scripts/dev.sh npm run lint -- <files>` + browser smoke (no JS unit runner; do **not** run whole-project `vue-tsc` in the dev container — it OOMs). **No money state changes** in M3 → no new `finance:audit-invariants` run is required, but the data-health panel *surfaces* invariant counts (Task 3) so confirm those counts match a manual `finance:audit-invariants` run during the smoke (Task 7).

---

## File Structure (M3)

**Created — backend read models:**
- `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitOverviewQuery.php` — KPI ribbon + 6 queue counts + phase.
- `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitDataHealthQuery.php` — 15 invariants + "Số dư khớp" + critical banner (deferred).
- `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitQueueRowsQuery.php` — top-N rows for one queue (Action Panel).
- `app/Modules/Finance/Queries/Operations/GetInstallmentPushFailureCountQuery.php` — the one missing queue count.
- `app/Modules/Finance/Support/Integrity/FinanceInvariantSampleResolver.php` — the drilldown sample→audit-target mapping (§4.4).
- `app/Modules/Finance/Support/FinanceCollectionPhase.php` — early/mid/late phase from semester + billing cycle dates.

**Created — backend HTTP:**
- `app/Modules/Finance/Http/Web/Admin/FinanceCockpitController.php` — `index` (page) + `queueRows` (JSON).

**Modified — backend:**
- `config/permission.php` + `database/seeders/InitialSetup/RoleAndPermissionSeeder.php` — add `view_finance_cockpit`.
- `app/Modules/Finance/Http/Requests/Audit/FinanceAuditSearchRequest.php` — add `finding_code`, `scope`, `sample_id`.
- `app/Modules/Finance/Http/Web/Admin/FinanceAuditWorkspaceController.php` — resolve `finding_code` → target via the resolver.
- `app/Modules/Finance/routes/web.php` — register cockpit routes.

**Created — frontend:**
- `resources/js/pages/Finance/Cockpit/Index.vue` — the Cockpit page.
- `resources/js/components/finance/cockpit/CriticalBanner.vue`
- `resources/js/components/finance/cockpit/QueueCard.vue`
- `resources/js/components/finance/cockpit/DataHealthPanel.vue`
- `resources/js/components/finance/cockpit/PhaseShortcuts.vue`
- `resources/js/components/finance/cockpit/ActionPanel.vue`

**Modified — frontend:**
- `resources/js/constants/finance-routes.ts` + `resources/js/utils/routes.ts` — cockpit route names/helpers.
- `resources/js/constants/menu-sidebar.ts` — repoint "Hôm nay" to the cockpit.
- `resources/js/types/finance.ts` — cockpit types.

**Tests:**
- `tests/Feature/Finance/Cockpit/CockpitOverviewTest.php`
- `tests/Feature/Finance/Cockpit/CockpitDataHealthTest.php`
- `tests/Feature/Finance/Cockpit/CockpitQueueRowsTest.php`
- `tests/Feature/Finance/Audit/InvariantDrilldownTest.php`

---

## Verified backend facts this plan builds on

- **`FinanceIntegrityAuditor`** (`app/Modules/Finance/Support/Integrity/FinanceIntegrityAuditor.php`): `summarize(?FinanceAuditScope $scope = null): list<array{code,severity,label,count:?int,error:?string}>` (null scope = global SQL counts, fast); `count(FinanceInvariant, ?scope): int`; `samples(FinanceInvariant, ?scope): list<int>`; `findForScope(FinanceAuditScope $scope): list<array{code,severity,label,kind,sample_ids}>` (requires non-empty scope). Scope placeholder `{scope}` → `1=1` (null) or `student_id IN (...)`.
- **`FinanceAuditScope(array $studentIds = [])`** — student-ids only; `isEmpty()`. Campus scope is a controller concern.
- **Invariant code → severity → sample-id-type** (from `FinanceInvariantRegistry`): INV-1 CRITICAL payment.id · INV-2 HIGH invoice.id · INV-3 CRITICAL charge.id · INV-4 CRITICAL invoice_line.id · INV-5 CRITICAL invoice_discount.id · INV-6 CRITICAL invoice.id · INV-7 HIGH student.id · INV-8 CRITICAL charge.id · INV-9 HIGH invoice.id · INV-10 HIGH payment.id · INV-11 HIGH webhook_event.id · INV-12 HIGH dng_payment_request.id · INV-13 HIGH charge.id · INV-14 HIGH dng_payment_request.id · INV-15 HIGH dng_payment_request_charges.id (pivot).
- **`SettlementService::snapshotDriftsFromCache(StudentInvoice, array $snapshot): bool`** + `CACHE_DRIFT_TOLERANCE = 0.01` — compares **both** `cached_paid_amount` vs `snapshot['paid']` **and** `cached_total_amount` vs `snapshot['net']`. `invoiceCacheDrifts(StudentInvoice): bool` is the convenience wrapper. This is the exact "Số dư khớp" definition.
- **Reusable queue summaries** (all `?int $semesterId`, campus-scoped internally): `GetDueItemsSummaryQuery` → `{upcoming_count,due_today_count,overdue_count,total_overdue_amount}`; `GetLifecycleDueExceptionSummaryQuery` → `{deferred_count,dropout_count,transfer_count,other_count,total_count,total_amount}`; `GetBillingExceptionCountsQuery` → `{missing_charge,retake_no_charge,defer_no_case,mismatch}`; `ListDngWebhookEventsQuery` stats → `{pending_count,mismatch_count,failed_count,…}`; `ListSettlementWorklistQuery` summary → `{total_unapplied_balance,ready_students,…}`; `GetBillingDashboardStatsQuery` → `{eligible_count,charged_count,uncharged_count,total_charges,total_credits,total_paid,total_balance,…}`.
- **Installment push failure**: `FinanceChargeInstallment` where `status = 'pending'` AND `last_push_error IS NOT NULL` (the `has_push_error` accessor). No existing count query.
- **`usePoll`** (`@inertiajs/vue3`, per `docs/inertiajs-vue-info.md`): `usePoll(intervalMs, options)`; options include router.reload opts (`only`, `onStart`, `onFinish`), `autoStart` (default true), `keepAlive` (default false → 90% throttle when tab backgrounded — keep default); returns `{ start, stop }`.
- **`BillingCycle`** (`app/Models/BillingCycle.php`): `semester_id, name, start_date, end_date, due_date, status(draft|active|closed)`; `isActive()`; `belongsTo(Semester)`. **`Semester`**: `start_date,end_date,is_active`; `Semester::getActiveSemester()`.
- **`StatsCard.vue`** (`resources/js/components/StatsCard.vue`): props `{ title, value, subItems?: {label,value}[] }`. **`Sheet`/`SheetContent`** (`@/components/ui/sheet`, `side` default `right`).

---

## Task 1: New permission `view_finance_cockpit` + seed

§8: the Cockpit gets its own view permission — **not** folded into `view_finance_operations_dashboard`; each widget still checks its source permission at render.

**Files:** `config/permission.php`, `database/seeders/InitialSetup/RoleAndPermissionSeeder.php`

- [ ] **Step 1: Declare the permission** — in `config/permission.php` `'finances'` array:

```php
    'view_finance_cockpit' => 'view_finance_cockpit',
```

- [ ] **Step 2: Map to roles** — in `RoleAndPermissionSeeder.php`, add `'view_finance_cockpit'` to the same finance-capable role lists that already receive `view_finance_operations_dashboard` / `view_finance_student_overview` (`truong_phong`, `can_bo`). super_admin gets it via the `sync(all)` call.

- [ ] **Step 3: Sync** — Run: `./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder`
Expected: reports `view_finance_cockpit` created.

- [ ] **Step 4: Commit**

```bash
git add config/permission.php database/seeders/InitialSetup/RoleAndPermissionSeeder.php
git commit -m "feat(finance): add view_finance_cockpit permission"
```

---

## Task 2: Backend — Cockpit overview (KPI ribbon + queues + phase) + queue rows

Assembles the existing summary queries into one light read model, adds the single missing count (installment push failures) and phase detection, and exposes a JSON endpoint for the Action Panel's on-demand top-N rows. Each queue carries `obeys_semester` + `scope_badge` (§3.1: webhook/unallocated are campus-wide and NOT hidden by semester change; due/lifecycle/charge-errors are semester-bound).

**Files:**
- Create: `app/Modules/Finance/Queries/Operations/GetInstallmentPushFailureCountQuery.php`
- Create: `app/Modules/Finance/Support/FinanceCollectionPhase.php`
- Create: `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitOverviewQuery.php`
- Create: `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitQueueRowsQuery.php`
- Create: `app/Modules/Finance/Http/Web/Admin/FinanceCockpitController.php`
- Modify: `app/Modules/Finance/routes/web.php`, `resources/js/constants/finance-routes.ts`, `resources/js/utils/routes.ts`
- Test: `tests/Feature/Finance/Cockpit/CockpitOverviewTest.php`, `tests/Feature/Finance/Cockpit/CockpitQueueRowsTest.php`

- [ ] **Step 1: Write the failing overview test**

Create `tests/Feature/Finance/Cockpit/CockpitOverviewTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantCockpit')) {
    function grantCockpit(array $codes): User
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
    Semester::factory()->create(['is_active' => true]);
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

it('renders the cockpit with kpi, queues and phase', function () {
    $user = grantCockpit(['view_finance_cockpit']);

    actingAs($user)->get('/finance/cockpit')
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Cockpit/Index')
            ->has('kpi.total_receivable')
            ->has('kpi.collected_pct')
            ->has('queues')
            ->has('phase.key')
            ->where('queues.0.key', 'webhook_errors')
            ->has('queues.0.count')
            ->has('queues.0.obeys_semester')
            ->has('queues.0.scope_badge'));
});

it('denies the cockpit without view_finance_cockpit', function () {
    $user = grantCockpit(['view_finance_operations_dashboard']);
    actingAs($user)->get('/finance/cockpit')->assertForbidden();
});

it('marks webhook/unallocated queues as semester-agnostic and due/lifecycle as semester-bound', function () {
    $user = grantCockpit(['view_finance_cockpit']);

    actingAs($user)->get('/finance/cockpit')
        ->assertInertia(function ($page) {
            $queues = collect($page->toArray()['props']['queues']);
            expect($queues->firstWhere('key', 'webhook_errors')['obeys_semester'])->toBeFalse();
            expect($queues->firstWhere('key', 'unallocated')['obeys_semester'])->toBeFalse();
            expect($queues->firstWhere('key', 'dng_due')['obeys_semester'])->toBeTrue();
            expect($queues->firstWhere('key', 'lifecycle')['obeys_semester'])->toBeTrue();
        });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Cockpit/CockpitOverviewTest.php`
Expected: FAIL — route missing.

- [ ] **Step 3: Installment push-failure count query**

Create `app/Modules/Finance/Queries/Operations/GetInstallmentPushFailureCountQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\FinanceChargeInstallment;

class GetInstallmentPushFailureCountQuery
{
    /** @return array{count:int,total_amount:float,oldest_at:?string} */
    public function handle(?int $semesterId = null): array
    {
        $base = FinanceChargeInstallment::query()
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->whereNotNull('last_push_error');

        if ($semesterId !== null) {
            $base->whereHas('charge', fn ($q) => $q->where('semester_id', $semesterId));
        }

        return [
            'count' => (clone $base)->count(),
            'total_amount' => (float) (clone $base)->sum('amount'),
            'oldest_at' => (clone $base)->orderBy('last_push_attempted_at')->value('last_push_attempted_at')?->toIso8601String(),
        ];
    }
}
```

> Confirm `FinanceCharge` has a `semester_id` column for the `whereHas('charge', …)` filter; if charges carry semester via the invoice instead, adjust to `whereHas('charge.invoice', …)`. The push-failure queue is `obeys_semester: false` by default (campus-wide), so the `$semesterId` filter is optional — passing null is the cockpit's default.

- [ ] **Step 4: Phase helper**

Create `app/Modules/Finance/Support/FinanceCollectionPhase.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\BillingCycle;
use App\Models\Semester;
use Carbon\Carbon;

/**
 * Infers an early/mid/late collection phase from the active semester (and the
 * active billing cycle, if any). Pure read; the operator can override via a
 * session value the cockpit controller passes in.
 */
class FinanceCollectionPhase
{
    /** @return array{key:string,label:string,source:string} */
    public static function infer(?string $override = null): array
    {
        if (in_array($override, ['early', 'mid', 'late'], true)) {
            return self::label($override, 'manual');
        }

        $semester = Semester::getActiveSemester();
        if ($semester === null || $semester->start_date === null || $semester->end_date === null) {
            return self::label('mid', 'default');
        }

        $start = Carbon::parse($semester->start_date);
        $end = Carbon::parse($semester->end_date);
        $now = Carbon::now();
        $total = max(1, $start->diffInDays($end));
        $elapsed = max(0, $start->diffInDays($now));
        $pct = min(100, (int) round(($elapsed / $total) * 100));

        $key = $pct <= 33 ? 'early' : ($pct >= 66 ? 'late' : 'mid');

        return self::label($key, 'inferred');
    }

    /** @return array{key:string,label:string,source:string} */
    private static function label(string $key, string $source): array
    {
        $labels = ['early' => 'Đầu kỳ', 'mid' => 'Giữa kỳ', 'late' => 'Cuối kỳ'];

        return ['key' => $key, 'label' => $labels[$key] ?? 'Giữa kỳ', 'source' => $source];
    }
}
```

- [ ] **Step 5: The overview query**

Create `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitOverviewQuery.php`. Reuses the existing summary queries; each queue declares `obeys_semester` + `scope_badge` + `action_url` + a per-widget `permission`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Cockpit;

use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Queries\Operations\GetBillingDashboardStatsQuery;
use App\Modules\Finance\Queries\Operations\GetBillingExceptionCountsQuery;
use App\Modules\Finance\Queries\Operations\GetDueItemsSummaryQuery;
use App\Modules\Finance\Queries\Operations\GetInstallmentPushFailureCountQuery;
use App\Modules\Finance\Queries\Operations\GetLifecycleDueExceptionSummaryQuery;
use App\Modules\Finance\Support\FinanceCollectionPhase;
use Illuminate\Support\Facades\DB;

/**
 * Light cockpit overview: KPI ribbon + the six "Cần xử lý" queue counts + phase.
 * Reuses existing campus-scoped summary queries; computes no money. Semester-bound
 * queues honor $semesterId; campus-wide ones (webhook/unallocated) ignore it so an
 * urgent item is never hidden by a semester change (§3.1).
 */
class GetFinanceCockpitOverviewQuery
{
    public function __construct(
        private GetBillingDashboardStatsQuery $dashboardStats,
        private GetDueItemsSummaryQuery $dueSummary,
        private GetLifecycleDueExceptionSummaryQuery $lifecycleSummary,
        private GetBillingExceptionCountsQuery $exceptionCounts,
        private GetInstallmentPushFailureCountQuery $installmentFailures,
    ) {}

    /** @return array<string,mixed> */
    public function handle(?int $semesterId, ?string $phaseOverride = null): array
    {
        return [
            'kpi' => $this->kpi($semesterId),
            'queues' => $this->queues($semesterId),
            'phase' => FinanceCollectionPhase::infer($phaseOverride),
        ];
    }

    /** @return array<string,mixed> */
    private function kpi(?int $semesterId): array
    {
        $s = $this->dashboardStats->handle($semesterId);
        $receivable = (float) $s['total_charges'] - (float) $s['total_credits'];
        $collectedPct = $receivable > 0 ? round(((float) $s['total_paid'] / $receivable) * 100, 1) : 0.0;

        return [
            'total_receivable' => $receivable,
            'total_collected' => (float) $s['total_paid'],
            'collected_pct' => $collectedPct,
            'uncharged_count' => (int) $s['uncharged_count'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function queues(?int $semesterId): array
    {
        // Campus-wide (never hidden by semester change).
        $webhook = DngWebhookEvent::query()
            ->whereIn('processing_status', [
                DngWebhookEvent::STATUS_FAILED_RETRYABLE,
                DngWebhookEvent::STATUS_FAILED_TERMINAL,
                DngWebhookEvent::STATUS_MISMATCH,
                DngWebhookEvent::STATUS_SKIPPED,
            ]);
        $webhookCount = (clone $webhook)->count();

        $unappliedCount = DB::table('payments')
            ->where('status', 'completed')
            ->whereColumn('amount', '>', DB::raw('(select coalesce(sum(pa.amount),0) from payment_applications pa where pa.payment_id = payments.id)'))
            ->count();

        // Semester-bound.
        $due = $this->dueSummary->handle($semesterId);
        $lifecycle = $this->lifecycleSummary->handle($semesterId);
        $exceptions = $this->exceptionCounts->handle($semesterId);
        $installments = $this->installmentFailures->handle(null);

        return [
            $this->queue('webhook_errors', 'Webhook DNG lỗi', $webhookCount, false, 'campus',
                'view_finance_dng_webhook_events', route('finance.dng.webhook-events.index'), $webhookCount > 0 ? 'critical' : 'normal'),
            $this->queue('unallocated', 'Tiền chờ phân bổ', $unappliedCount, false, 'campus',
                'allocate_finance_payment', route('finance.operations.settlement.index'), $unappliedCount > 0 ? 'action' : 'normal'),
            $this->queue('dng_due', 'DNG đến hạn', (int) $due['overdue_count'] + (int) $due['due_today_count'], true, 'semester',
                'view_finance_operations_due_calendar', route('finance.operations.due-calendar'), (int) $due['overdue_count'] > 0 ? 'action' : 'normal'),
            $this->queue('lifecycle', 'Ngoại lệ lifecycle chờ review', (int) $lifecycle['total_count'], true, 'semester',
                'view_finance_operations_due_calendar', route('finance.operations.lifecycle-exceptions'), 'action'),
            $this->queue('charge_errors', 'Sai sót sinh phí', (int) array_sum($exceptions), true, 'semester',
                'view_finance_operations_exceptions', route('finance.operations.exceptions'), 'action'),
            $this->queue('installment_failures', 'Installment đẩy thất bại', (int) $installments['count'], false, 'campus',
                'create_finance_payments', route('finance.operations.dng-worklist'), (int) $installments['count'] > 0 ? 'action' : 'normal'),
        ];
    }

    /** @return array<string,mixed> */
    private function queue(string $key, string $label, int $count, bool $obeysSemester, string $scopeBadge, string $permission, string $actionUrl, string $severity): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'obeys_semester' => $obeysSemester,
            'scope_badge' => $scopeBadge,
            'permission' => $permission,
            'action_url' => $actionUrl,
            'severity' => $severity, // 'critical' | 'action' | 'normal'
        ];
    }
}
```

> The `unallocated` count uses a correlated subquery to avoid loading payments into PHP. Confirm the `payment_applications` table/column names by grepping the `PaymentApplication` model; adjust the raw subquery to match. If the project forbids `DB::raw` correlated subqueries by convention, fall back to `ListSettlementWorklistQuery`'s `summary.ready_students`.

- [ ] **Step 6: The queue-rows query (Action Panel)**

Create `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitQueueRowsQuery.php` — returns the top-N rows for one queue by delegating to the existing list query, mapped to a uniform row shape (`{id, title, subtitle, age, student_id, primary_action}`):

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Cockpit;

use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Models\FinanceChargeInstallment;

/**
 * Top-N rows for a single cockpit queue, fetched on demand when the Action Panel
 * opens. Bounded by $limit; reuses existing models/queries. Read-only.
 */
class GetFinanceCockpitQueueRowsQuery
{
    private const LIMIT = 15;

    /** @return list<array<string,mixed>> */
    public function handle(string $queueKey, ?int $semesterId): array
    {
        return match ($queueKey) {
            'webhook_errors' => $this->webhookRows(),
            'installment_failures' => $this->installmentRows(),
            // Other queues deep-link to their existing worklist pages (no panel rows
            // needed in M3); the cockpit card's action_url handles those.
            default => [],
        };
    }

    /** @return list<array<string,mixed>> */
    private function webhookRows(): array
    {
        return DngWebhookEvent::query()
            ->whereIn('processing_status', [
                DngWebhookEvent::STATUS_FAILED_RETRYABLE,
                DngWebhookEvent::STATUS_FAILED_TERMINAL,
                DngWebhookEvent::STATUS_MISMATCH,
                DngWebhookEvent::STATUS_SKIPPED,
            ])
            ->orderBy('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (DngWebhookEvent $e) => [
                'id' => (int) $e->id,
                'title' => 'Webhook #'.$e->id.' · '.$e->processing_status,
                'subtitle' => (string) ($e->error_message ?? ''),
                'age' => $e->created_at?->diffForHumans(),
                'student_id' => null,
                'primary_action' => [
                    'kind' => 'retry_webhook',
                    'url' => route('finance.dng.webhook-events.retry', ['dngWebhookEvent' => $e->id]),
                    'label' => 'Retry',
                ],
            ])->all();
    }

    /** @return list<array<string,mixed>> */
    private function installmentRows(): array
    {
        return FinanceChargeInstallment::query()
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->whereNotNull('last_push_error')
            ->orderBy('last_push_attempted_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (FinanceChargeInstallment $i) => [
                'id' => (int) $i->id,
                'title' => 'Kỳ '.$i->installment_no.' · charge #'.$i->finance_charge_id,
                'subtitle' => (string) ($i->last_push_error ?? ''),
                'age' => $i->last_push_attempted_at?->diffForHumans(),
                'student_id' => null,
                'primary_action' => [
                    'kind' => 'retry_installment',
                    'url' => route('finance.charges.installments.retry-push', ['charge' => $i->finance_charge_id, 'installment' => $i->id]),
                    'label' => 'Đẩy lại',
                ],
            ])->all();
    }
}
```

> Use the `FinanceChargeInstallment` FQCN that exists in the repo (`App\Models\FinanceChargeInstallment` per the model path verified earlier — adjust the `use` if it lives under `App\Modules\Finance\Models`).

- [ ] **Step 7: The controller**

Create `app/Modules/Finance/Http/Web/Admin/FinanceCockpitController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Finance\Queries\Cockpit\GetFinanceCockpitDataHealthQuery;
use App\Modules\Finance\Queries\Cockpit\GetFinanceCockpitOverviewQuery;
use App\Modules\Finance\Queries\Cockpit\GetFinanceCockpitQueueRowsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceCockpitController extends Controller
{
    public function index(
        Request $request,
        GetFinanceCockpitOverviewQuery $overview,
        GetFinanceCockpitDataHealthQuery $dataHealth,
    ): Response {
        $semesterId = $this->selectedSemesterId();
        $phaseOverride = $request->session()->get('finance_cockpit_phase');

        $props = $overview->handle($semesterId, $phaseOverride);

        // Data health (15 invariants + Số dư khớp + critical banner) is heavier and
        // campus-scoped → deferred + manually refreshed (not polled).
        $props['data_health'] = Inertia::defer(fn () => $dataHealth->handle(
            $this->currentCampusId(),
            $request->user()?->can('view_finance_all_campus') ?? false,
        ));

        return Inertia::render('Finance/Cockpit/Index', $props);
    }

    public function queueRows(string $queue, GetFinanceCockpitQueueRowsQuery $query): JsonResponse
    {
        return ApiResponse::success([
            'queue' => $queue,
            'rows' => $query->handle($queue, $this->selectedSemesterId()),
        ]);
    }

    private function selectedSemesterId(): ?int
    {
        $id = session('current_semester_id');

        return $id !== null ? (int) $id : null;
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

> `GetFinanceCockpitDataHealthQuery` is created in Task 3; this controller references it now so the route compiles. Implement Task 3 before running the full suite, or temporarily stub the deferred prop as `[]` and replace it in Task 3. (Recommended: do Task 3's query class first, then this — but the overview test in this task does not assert `data_health`, so a stub is acceptable mid-task.)

- [ ] **Step 8: Routes (constants + helper + web)**

`finance-routes.ts` `FINANCE_ROUTE_NAMES`:

```typescript
    COCKPIT_INDEX: 'finance.cockpit.index',
    COCKPIT_QUEUE_ROWS: 'finance.cockpit.queue-rows',
    COCKPIT_PHASE: 'finance.cockpit.phase',
```

`utils/routes.ts` `financeRoutes`:

```typescript
    cockpit: {
        index: () => route(FINANCE_ROUTE_NAMES.COCKPIT_INDEX),
        queueRows: (queue: string) => route(FINANCE_ROUTE_NAMES.COCKPIT_QUEUE_ROWS, { queue }),
        phase: () => route(FINANCE_ROUTE_NAMES.COCKPIT_PHASE),
    },
```

`app/Modules/Finance/routes/web.php` (import + routes inside the finance group):

```php
use App\Modules\Finance\Http\Web\Admin\FinanceCockpitController;
```

```php
    // Cockpit "Hôm nay"
    Route::get('/cockpit', [FinanceCockpitController::class, 'index'])
        ->middleware('can:view_finance_cockpit')
        ->name('cockpit.index');
    Route::get('/cockpit/queue/{queue}', [FinanceCockpitController::class, 'queueRows'])
        ->middleware('can:view_finance_cockpit')
        ->name('cockpit.queue-rows');
    Route::post('/cockpit/phase', [FinanceCockpitController::class, 'setPhase'])
        ->middleware('can:view_finance_cockpit')
        ->name('cockpit.phase');
```

Add the `setPhase` method to the controller (writes the manual override to session):

```php
    public function setPhase(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate(['phase' => ['nullable', 'in:early,mid,late']]);
        $request->session()->put('finance_cockpit_phase', $validated['phase'] ?? null);

        return back();
    }
```

- [ ] **Step 9: Queue-rows test**

Create `tests/Feature/Finance/Cockpit/CockpitQueueRowsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
    $user = User::factory()->create();
    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn(['view_finance_cockpit']);
    app()->singleton(PermissionService::class, fn () => $mock);
    $this->user = $user;
});

it('returns top rows for the webhook queue with a retry action', function () {
    DngWebhookEvent::create([
        'processing_status' => DngWebhookEvent::STATUS_MISMATCH,
        'event_type' => 'payment', 'payload' => [], 'payload_hash' => 'h1',
    ]);

    actingAs($this->user)->getJson('/finance/cockpit/queue/webhook_errors')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.queue', 'webhook_errors')
        ->assertJsonPath('data.rows.0.primary_action.kind', 'retry_webhook');
});
```

> Confirm `DngWebhookEvent::create` required columns by grepping its `$fillable`; supply the minimum valid set (the assertion only needs one mismatch row to exist).

- [ ] **Step 10: Run both tests**

Run: `./scripts/dev.sh test tests/Feature/Finance/Cockpit/CockpitOverviewTest.php tests/Feature/Finance/Cockpit/CockpitQueueRowsTest.php`
Expected: PASS (after Task 3's data-health query exists or is stubbed).

- [ ] **Step 11: Lint TS + commit**

```bash
./scripts/dev.sh npm run lint -- resources/js/constants/finance-routes.ts resources/js/utils/routes.ts
git add app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitOverviewQuery.php \
  app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitQueueRowsQuery.php \
  app/Modules/Finance/Queries/Operations/GetInstallmentPushFailureCountQuery.php \
  app/Modules/Finance/Support/FinanceCollectionPhase.php \
  app/Modules/Finance/Http/Web/Admin/FinanceCockpitController.php \
  app/Modules/Finance/routes/web.php resources/js/constants/finance-routes.ts resources/js/utils/routes.ts \
  tests/Feature/Finance/Cockpit/CockpitOverviewTest.php tests/Feature/Finance/Cockpit/CockpitQueueRowsTest.php
git commit -m "feat(finance): add cockpit overview, queue counts, and phase detection"
```

---

## Task 3: Backend — data-health panel (15 invariants + Số dư khớp + critical banner)

§4.4. Deferred + campus-scoped (or all-campus with permission). Uses the existing `FinanceIntegrityAuditor`; the "Số dư khớp" ratio uses `SettlementService::snapshotDriftsFromCache` exactly (both cached_paid and cached_total). Each invariant tile links to the Audit Workspace drilldown (`finding_code`), wired in Task 4.

**Files:**
- Create: `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitDataHealthQuery.php`
- Test: `tests/Feature/Finance/Cockpit/CockpitDataHealthTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Cockpit/CockpitDataHealthTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

function grantCockpitHealth(array $codes): User
{
    $user = User::factory()->create();
    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn($codes);
    app()->singleton(PermissionService::class, fn () => $mock);

    return $user;
}

it('defers a data_health payload with invariants and a balance-match metric', function () {
    $user = grantCockpitHealth(['view_finance_cockpit', 'view_finance_audit_workspace']);

    actingAs($user)->get('/finance/cockpit?only[]=data_health', ['X-Inertia' => true, 'X-Inertia-Partial-Component' => 'Finance/Cockpit/Index', 'X-Inertia-Partial-Data' => 'data_health'])
        ->assertInertia(fn ($page) => $page
            ->has('data_health.critical_count')
            ->has('data_health.invariants')
            ->has('data_health.balance_match.match_pct')
            ->has('data_health.balance_match.denominator')
            ->where('data_health.scope_badge', 'campus'));
});

it('uses an all-system scope badge for an all-campus user', function () {
    $user = grantCockpitHealth(['view_finance_cockpit', 'view_finance_audit_workspace', 'view_finance_all_campus']);

    actingAs($user)->get('/finance/cockpit', ['X-Inertia' => true, 'X-Inertia-Partial-Component' => 'Finance/Cockpit/Index', 'X-Inertia-Partial-Data' => 'data_health'])
        ->assertInertia(fn ($page) => $page->where('data_health.scope_badge', 'all_campus'));
});
```

> The partial-reload header trio forces Inertia to resolve the deferred `data_health` prop. If the assertion plumbing differs in this repo's test helpers, instead unit-test `GetFinanceCockpitDataHealthQuery::handle()` directly (construct it from the container, assert the array shape) — that is the higher-signal test and avoids partial-reload header fiddliness.

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Cockpit/CockpitDataHealthTest.php`
Expected: FAIL — query class missing / `data_health` empty.

- [ ] **Step 3: Implement the data-health query**

Create `app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitDataHealthQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Cockpit;

use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;

/**
 * §4.4 data-health: the 15 invariants as trust signals + the "Số dư khớp" ratio.
 * Campus-scoped by default (student-id scope); all-campus runs the auditor globally.
 * Deferred + manually refreshed — NOT polled — because the campus-scoped invariant
 * SQL and the snapshot drift iteration are heavier than the queue counts.
 */
class GetFinanceCockpitDataHealthQuery
{
    public function __construct(
        private FinanceIntegrityAuditor $auditor,
        private SettlementService $settlement,
    ) {}

    /** @return array<string,mixed> */
    public function handle(?int $campusId, bool $allCampus): array
    {
        [$scope, $studentIds, $scopeBadge] = $this->resolveScope($campusId, $allCampus);

        $summary = $this->auditor->summarize($scope); // null scope = global SQL counts

        $invariants = array_values(array_filter(array_map(function (array $row): ?array {
            $count = $row['count'];
            if ($count === null) {
                // failed-to-run → surface as an error tile so it is never silently 0.
                return ['code' => $row['code'], 'severity' => 'ERROR', 'label' => $row['label'], 'count' => null, 'error' => $row['error']];
            }
            if ($count <= 0) {
                return null;
            }

            return ['code' => $row['code'], 'severity' => $row['severity'], 'label' => $row['label'], 'count' => (int) $count, 'error' => null];
        }, $summary)));

        $criticalCount = array_sum(array_map(
            fn (array $i) => $i['severity'] === 'CRITICAL' ? $i['count'] : 0,
            array_filter($invariants, fn (array $i) => $i['count'] !== null),
        ));

        return [
            'scope_badge' => $scopeBadge,
            'critical_count' => (int) $criticalCount,
            'invariants' => $invariants,
            'balance_match' => $this->balanceMatch($studentIds, $allCampus),
        ];
    }

    /**
     * @return array{0:?FinanceAuditScope,1:?list<int>,2:string}
     */
    private function resolveScope(?int $campusId, bool $allCampus): array
    {
        if ($allCampus) {
            return [null, null, 'all_campus']; // global SQL counts
        }

        if ($campusId === null) {
            return [new FinanceAuditScope([]), [], 'campus']; // empty → summarize treats as global; counts will be global but badge stays campus
        }

        $ids = Student::query()->where('campus_id', $campusId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return [new FinanceAuditScope($ids), $ids, 'campus'];
    }

    /**
     * "Số dư khớp" — ratio of invoices NOT drifting (both cached_paid vs snapshot.paid
     * AND cached_total vs snapshot.net), per SettlementService::snapshotDriftsFromCache.
     *
     * @param  list<int>|null  $studentIds  null = all-campus (global)
     * @return array{match_pct:float,matched:int,denominator:int}
     */
    private function balanceMatch(?array $studentIds, bool $allCampus): array
    {
        $query = StudentInvoice::query()
            ->with(['invoiceLines.charge', 'invoiceLines.paymentApplications', 'invoiceLines.discountAllocations']);

        if (! $allCampus) {
            // campus scope; empty id set → no invoices (avoids a global scan for a campus badge).
            $query->whereIn('student_id', $studentIds ?? []);
        }

        $invoices = $query->get();
        $denominator = $invoices->count();
        if ($denominator === 0) {
            return ['match_pct' => 100.0, 'matched' => 0, 'denominator' => 0];
        }

        $matched = $invoices->reject(fn (StudentInvoice $inv) => $this->settlement->invoiceCacheDrifts($inv))->count();

        return [
            'match_pct' => round(($matched / $denominator) * 100, 1),
            'matched' => $matched,
            'denominator' => $denominator,
        ];
    }
}
```

> **Performance note (record in the trace):** the campus path builds a `student_id IN (...)` of the campus's student ids and iterates `invoiceCacheDrifts` per invoice. This is why `data_health` is **deferred + manual-refresh, never polled**. Design §11 leaves volume thresholds open; if a campus is large enough that this is slow, the follow-up is to push drift detection into SQL (an INV-2-style check extended to `cached_total`) — out of scope for M3, note it in the backlog (`./scripts/harness backlog add`).
> Confirm `FinanceInvariantRegistry`/auditor sample accessor names are not needed here — this query only uses `summarize()` (counts), so no `FinanceInvariant` objects are touched. Sample resolution lives in Task 4.

- [ ] **Step 4: Run the test to verify it passes**

Run: `./scripts/dev.sh test tests/Feature/Finance/Cockpit/CockpitDataHealthTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Finance/Queries/Cockpit/GetFinanceCockpitDataHealthQuery.php \
  tests/Feature/Finance/Cockpit/CockpitDataHealthTest.php
git commit -m "feat(finance): add cockpit data-health panel (15 invariants + balance match)"
```

---

## Task 4: Backend — invariant drilldown contract (the M3-owned cross-dependency)

§4.4 + §10: the Audit Workspace currently filters by `q/target_type/target_id/semester_id/billing_cycle_id` only. This task extends it (option (a)) with `finding_code` (+ optional `sample_id`, `scope`) and adds the **sample-type → audit-target mapping** so a cockpit tile can deep-link "show me a violating record for INV-X". Read-only.

**Files:**
- Create: `app/Modules/Finance/Support/Integrity/FinanceInvariantSampleResolver.php`
- Modify: `app/Modules/Finance/Http/Requests/Audit/FinanceAuditSearchRequest.php`
- Modify: `app/Modules/Finance/Http/Web/Admin/FinanceAuditWorkspaceController.php`
- Test: `tests/Feature/Finance/Audit/InvariantDrilldownTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Finance/Audit/InvariantDrilldownTest.php`:

```php
<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Integrity\FinanceInvariantSampleResolver;

it('maps each invariant code to its audit target type or a list view', function () {
    $resolver = new FinanceInvariantSampleResolver();

    expect($resolver->targetTypeFor('INV-1'))->toBe('payment');   // payment.id
    expect($resolver->targetTypeFor('INV-3'))->toBe('charge');    // fc.id
    expect($resolver->targetTypeFor('INV-2'))->toBe('invoice');   // si.id
    expect($resolver->targetTypeFor('INV-12'))->toBe('dng');      // dpr.id
    expect($resolver->targetTypeFor('INV-4'))->toBe('invoice');   // il.id → invoice (resolved up)
    expect($resolver->targetTypeFor('INV-5'))->toBe('invoice');   // idc.id → invoice
    expect($resolver->targetTypeFor('INV-7'))->toBe('student');   // scholarship → student
    expect($resolver->targetTypeFor('INV-11'))->toBeNull();       // webhook event → list view, no audit target
    expect($resolver->targetTypeFor('INV-15'))->toBe('dng');      // pivot → dng
    expect($resolver->listUrlFor('INV-11'))->not->toBeNull();     // webhook events list
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./scripts/dev.sh test tests/Feature/Finance/Audit/InvariantDrilldownTest.php`
Expected: FAIL — resolver class missing.

- [ ] **Step 3: The sample resolver (the mapping table, §4.4)**

Create `app/Modules/Finance/Support/Integrity/FinanceInvariantSampleResolver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

use App\Models\InvoiceLine;
use Illuminate\Support\Facades\Route;

/**
 * Maps an invariant finding to an Audit Workspace target (§4.4). Most samples are
 * already a graphable target type; a few are resolved "up" (invoice_line → invoice,
 * invoice_discount → invoice, pivot → dng) and a couple have no graph target and
 * link to a list view instead (webhook event).
 */
class FinanceInvariantSampleResolver
{
    /** finding_code => audit target_type ('student'|'invoice'|'payment'|'charge'|'dng'), or null = no audit target */
    private const TARGET_TYPE = [
        'INV-1' => 'payment', 'INV-10' => 'payment',
        'INV-2' => 'invoice', 'INV-6' => 'invoice', 'INV-9' => 'invoice',
        'INV-3' => 'charge', 'INV-8' => 'charge', 'INV-13' => 'charge',
        'INV-4' => 'invoice',  // sample is invoice_line.id → resolve to its invoice
        'INV-5' => 'invoice',  // sample is invoice_discount.id → resolve to its invoice
        'INV-7' => 'student',
        'INV-12' => 'dng', 'INV-14' => 'dng', 'INV-15' => 'dng', // INV-15 sample is pivot → resolve to dng
        'INV-11' => null,      // webhook event → list view, not a graph target
    ];

    public function targetTypeFor(string $code): ?string
    {
        return self::TARGET_TYPE[$code] ?? null;
    }

    /** A list-view fallback URL for codes with no audit target. */
    public function listUrlFor(string $code): ?string
    {
        if ($code === 'INV-11' && Route::has('finance.dng.webhook-events.index')) {
            return route('finance.dng.webhook-events.index');
        }

        return null;
    }

    /**
     * Resolve a raw sample id (whose native type is the invariant's sample-id-type)
     * to an audit target id of the mapped target_type. Returns null when the sample
     * cannot be resolved up (caller then falls back to listUrlFor / empty).
     */
    public function resolveTargetId(string $code, int $sampleId): ?int
    {
        return match ($code) {
            // sample id already IS the target id for these
            'INV-1', 'INV-10', 'INV-2', 'INV-6', 'INV-9', 'INV-3', 'INV-8', 'INV-13', 'INV-7', 'INV-12', 'INV-14' => $sampleId,
            // invoice_line.id → invoice_id
            'INV-4' => InvoiceLine::find($sampleId)?->invoice_id !== null ? (int) InvoiceLine::find($sampleId)->invoice_id : null,
            // invoice_discount.id → invoice_id (resolve via the discount's invoice)
            'INV-5' => $this->invoiceOfDiscount($sampleId),
            // pivot dng_payment_request_charges.id → dng_payment_request_id
            'INV-15' => $this->dngOfPivot($sampleId),
            default => null,
        };
    }

    private function invoiceOfDiscount(int $discountId): ?int
    {
        // InvoiceDiscount → invoice_id. Adjust model FQCN/relation to the repo.
        $model = \App\Models\InvoiceDiscount::find($discountId);

        return $model?->invoice_id !== null ? (int) $model->invoice_id : null;
    }

    private function dngOfPivot(int $pivotId): ?int
    {
        $row = \Illuminate\Support\Facades\DB::table('dng_payment_request_charges')->where('id', $pivotId)->first();

        return $row?->dng_payment_request_id !== null ? (int) $row->dng_payment_request_id : null;
    }
}
```

> Confirm exact model FQCNs/columns by grepping: `InvoiceLine::invoice_id`, `App\Models\InvoiceDiscount` (or wherever `idc` lives) + its `invoice_id`, and the pivot table name `dng_payment_request_charges` + its `dng_payment_request_id` column. The unit test above only exercises the static `targetTypeFor`/`listUrlFor` maps, so it passes without DB; the `resolveTargetId` lookups are covered by the controller integration assertion in Step 6.

- [ ] **Step 4: Extend the search request**

In `app/Modules/Finance/Http/Requests/Audit/FinanceAuditSearchRequest.php`, add to `rules()`:

```php
            'finding_code' => 'nullable|string|regex:/^INV-\\d{1,2}$/',
            'sample_id' => 'nullable|integer|min:1',
            'scope' => 'nullable|string|in:campus,all_campus',
```

- [ ] **Step 5: Resolve the finding in the controller**

In `app/Modules/Finance/Http/Web/Admin/FinanceAuditWorkspaceController.php`, inject the resolver + auditor + registry and, at the top of `index()` (before the existing `resolve(...)` call), translate a `finding_code` into an explicit `target_type`/`target_id`:

```php
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;
use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;
use App\Modules\Finance\Support\Integrity\FinanceInvariantSampleResolver;
```

Add a private helper and call it in `index()`:

```php
    /**
     * If a finding_code is present, pick a sample (the given sample_id, else the
     * first sample the auditor finds) and map it to an audit target. Returns the
     * mutated $data with target_type/target_id set, plus an optional redirect url
     * for codes that have no graph target (e.g. webhook events).
     *
     * @param  array<string,mixed>  $data
     * @return array{0:array<string,mixed>,1:?string}
     */
    private function applyFindingDrilldown(
        array $data,
        ?int $campusId,
        FinanceInvariantRegistry $registry,
        FinanceIntegrityAuditor $auditor,
        FinanceInvariantSampleResolver $resolver,
    ): array {
        $code = $data['finding_code'] ?? null;
        if ($code === null) {
            return [$data, null];
        }

        $listUrl = $resolver->listUrlFor($code);
        $targetType = $resolver->targetTypeFor($code);
        if ($targetType === null) {
            return [$data, $listUrl]; // no graph target → caller redirects to the list view
        }

        $sampleId = isset($data['sample_id']) ? (int) $data['sample_id'] : null;
        if ($sampleId === null) {
            // Pick the first sample for this finding within the resolved scope.
            $invariant = $registry->find($code);
            $scope = ($data['scope'] ?? 'campus') === 'all_campus'
                ? null
                : new \App\Modules\Finance\Support\Integrity\FinanceAuditScope($this->campusStudentIds($campusId));
            $sampleId = $invariant !== null ? ($auditor->samples($invariant, $scope)[0] ?? null) : null;
        }

        if ($sampleId === null) {
            return [$data, null];
        }

        $targetId = $resolver->resolveTargetId($code, $sampleId);
        if ($targetId === null) {
            return [$data, $listUrl];
        }

        $data['target_type'] = $targetType;
        $data['target_id'] = $targetId;

        return [$data, null];
    }

    /** @return list<int> */
    private function campusStudentIds(?int $campusId): array
    {
        if ($campusId === null) {
            return [];
        }

        return \App\Models\Student::query()->where('campus_id', $campusId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
```

In `index()`, after computing `$campusId` and `$data`, insert:

```php
        [$data, $redirectUrl] = $this->applyFindingDrilldown($data, $campusId, $registry, $auditor, $resolver);
        if ($redirectUrl !== null) {
            return Inertia::location($redirectUrl); // codes with no audit target → list view
        }
```

(Add `FinanceInvariantRegistry $registry, FinanceIntegrityAuditor $auditor, FinanceInvariantSampleResolver $resolver` to the `index()` signature.)

> Confirm `FinanceInvariantRegistry::find(string $code): ?FinanceInvariant` exists (grep the registry for a `find`/`get`/`byCode` accessor; the auditor iterates the registry, so an accessor or an iterable getter exists — use whatever is there, e.g. iterate `FinanceInvariantRegistry::all()` and match `->code()`). `Inertia::location()` issues a client-side redirect for the no-target case; if the repo prefers a normal redirect, use `redirect()->away($redirectUrl)` is wrong for internal — use `Inertia::location(route(...))` or a server `redirect($redirectUrl)` (a GET to an Inertia page works with `redirect()`).

- [ ] **Step 6: Add a controller integration assertion**

Append to `tests/Feature/Finance/Audit/InvariantDrilldownTest.php` (uses the audit workspace setup from `FinanceAuditWorkspacePageTest`):

```php
it('resolves a charge-typed finding to an audit charge target', function () {
    // Arrange: a campus, a student with a finance charge that violates INV-3
    // (active positive charge not linked to exactly one active invoice line).
    // Reuse the audit workspace test fixtures for campus/session/permission.
    // ... (mirror FinanceAuditWorkspacePageTest beforeEach + grant view_finance_audit_workspace)
    // Act + Assert: a finding_code drilldown sets target_type=charge.
})->skip('Wire fixtures from FinanceAuditWorkspacePageTest; asserts resolution.status=single + target.type=charge for an INV-3 sample.');
```

> Replace the skipped placeholder with a real fixture once you confirm the cheapest way to manufacture an INV-3 sample (an active positive `FinanceCharge` with no active invoice line). If manufacturing a real invariant violation is expensive, keep the unit-level `resolveTargetId` covered by a direct query test instead (construct the resolver, seed one `InvoiceLine`, assert `resolveTargetId('INV-4', $line->id) === $line->invoice_id`). Do not ship the `skip`.

- [ ] **Step 7: Run + commit**

Run: `./scripts/dev.sh test tests/Feature/Finance/Audit/InvariantDrilldownTest.php`
Expected: PASS (mapping unit test green; the integration assertion green once fixtured).

```bash
git add app/Modules/Finance/Support/Integrity/FinanceInvariantSampleResolver.php \
  app/Modules/Finance/Http/Requests/Audit/FinanceAuditSearchRequest.php \
  app/Modules/Finance/Http/Web/Admin/FinanceAuditWorkspaceController.php \
  tests/Feature/Finance/Audit/InvariantDrilldownTest.php
git commit -m "feat(finance): add invariant drilldown contract to audit workspace"
```

---

## Task 5: Frontend — Cockpit page (banner, KPI, queues, data-health, phase) + polling

**Files:**
- Modify: `resources/js/types/finance.ts`
- Create: `resources/js/components/finance/cockpit/CriticalBanner.vue`, `QueueCard.vue`, `DataHealthPanel.vue`, `PhaseShortcuts.vue`
- Create: `resources/js/pages/Finance/Cockpit/Index.vue`

- [ ] **Step 1: Add cockpit types** to `resources/js/types/finance.ts`:

```typescript
export interface CockpitKpi { total_receivable: number; total_collected: number; collected_pct: number; uncharged_count: number }
export interface CockpitQueue {
    key: string; label: string; count: number;
    obeys_semester: boolean; scope_badge: 'campus' | 'semester' | 'multi';
    permission: string; action_url: string; severity: 'critical' | 'action' | 'normal';
}
export interface CockpitPhase { key: 'early' | 'mid' | 'late'; label: string; source: string }
export interface CockpitInvariant { code: string; severity: string; label: string; count: number | null; error: string | null }
export interface CockpitDataHealth {
    scope_badge: 'campus' | 'all_campus';
    critical_count: number;
    invariants: CockpitInvariant[];
    balance_match: { match_pct: number; matched: number; denominator: number };
}
export interface CockpitQueueRow {
    id: number; title: string; subtitle: string; age: string | null; student_id: number | null;
    primary_action: { kind: string; url: string; label: string };
}
```

- [ ] **Step 2: CriticalBanner** — `resources/js/components/finance/cockpit/CriticalBanner.vue`:

```vue
<script setup lang="ts">
import { financeRoutes } from '@/utils/routes';
import { Link } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';

defineProps<{ criticalCount: number }>();
</script>

<template>
    <div v-if="criticalCount > 0" class="flex items-center justify-between rounded-md border border-red-300 bg-red-50 p-3 dark:bg-red-950">
        <div class="flex items-center gap-2 text-red-800 dark:text-red-200">
            <ShieldAlert class="size-5" />
            <span class="font-medium">{{ criticalCount }} vi phạm toàn vẹn tiền nghiêm trọng cần xử lý ngay.</span>
        </div>
        <Link :href="financeRoutes.audit()" class="text-sm font-medium text-red-700 underline dark:text-red-300">Xem chi tiết</Link>
    </div>
</template>
```

- [ ] **Step 3: QueueCard** — `resources/js/components/finance/cockpit/QueueCard.vue` (fixed anatomy: title+icon+count+severity dot, scope badge, primary action):

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { CockpitQueue } from '@/types/finance';

defineProps<{ queue: CockpitQueue }>();
const emit = defineEmits<{ (e: 'open', queue: CockpitQueue): void }>();

const dotClass: Record<string, string> = {
    critical: 'bg-red-500',
    action: 'bg-orange-500',
    normal: 'bg-slate-300',
};
const badgeLabel: Record<string, string> = { campus: 'Toàn campus', semester: 'Theo kỳ', multi: 'Đa kỳ' };
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-1">
            <CardTitle class="flex items-center gap-2 text-sm">
                <span class="size-2 rounded-full" :class="dotClass[queue.severity]" />
                {{ queue.label }}
            </CardTitle>
            <Badge variant="outline" class="text-xs">{{ badgeLabel[queue.scope_badge] }}</Badge>
        </CardHeader>
        <CardContent class="flex items-center justify-between">
            <span class="text-2xl font-bold tabular-nums">{{ queue.count }}</span>
            <Button size="sm" variant="outline" @click="emit('open', queue)">Xử lý</Button>
        </CardContent>
    </Card>
</template>
```

- [ ] **Step 4: DataHealthPanel** — `resources/js/components/finance/cockpit/DataHealthPanel.vue` (drilldown link per invariant tile):

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { CockpitDataHealth } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Deferred, Link } from '@inertiajs/vue3';

const props = defineProps<{ dataHealth?: CockpitDataHealth }>();

const drilldown = (code: string, scope: string): string =>
    `${financeRoutes.audit()}?finding_code=${code}&scope=${scope}`;

const sev = (s: string): string =>
    s === 'CRITICAL' ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
        : s === 'ERROR' ? 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
            : 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200';
</script>

<template>
    <Card>
        <CardHeader class="pb-1"><CardTitle class="text-sm">Sức khỏe dữ liệu</CardTitle></CardHeader>
        <CardContent>
            <Deferred data="data_health">
                <template #fallback><Skeleton class="h-32 w-full" /></template>
                <div v-if="dataHealth" class="space-y-3">
                    <!-- Số dư khớp -->
                    <div class="flex items-center justify-between text-sm">
                        <span :title="`Mẫu số = ${dataHealth.balance_match.denominator} hóa đơn active trong phạm vi`">Số dư khớp</span>
                        <strong class="tabular-nums" :class="dataHealth.balance_match.match_pct >= 100 ? 'text-green-700 dark:text-green-300' : 'text-orange-700 dark:text-orange-300'">
                            {{ dataHealth.balance_match.match_pct }}%
                        </strong>
                    </div>
                    <!-- Invariant tiles -->
                    <ul class="space-y-1">
                        <li v-for="inv in dataHealth.invariants" :key="inv.code" class="flex items-center justify-between rounded-md border p-2 text-sm">
                            <span>
                                <Badge :class="sev(inv.severity)" class="mr-2">{{ inv.code }}</Badge>
                                {{ inv.label }}
                            </span>
                            <Link :href="drilldown(inv.code, dataHealth.scope_badge)" class="text-xs font-medium underline">
                                {{ inv.error ? 'lỗi kiểm tra' : `${inv.count} mẫu` }} ↗
                            </Link>
                        </li>
                    </ul>
                    <p v-if="dataHealth.invariants.length === 0" class="text-muted-foreground text-sm">Không có vi phạm.</p>
                </div>
            </Deferred>
        </CardContent>
    </Card>
</template>
```

- [ ] **Step 5: PhaseShortcuts** — `resources/js/components/finance/cockpit/PhaseShortcuts.vue` (§4.5 adaptive + manual switch):

```vue
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { CockpitPhase } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps<{ phase: CockpitPhase }>();

const shortcuts = {
    early: [
        { label: 'Sinh phí hàng loạt', href: financeRoutes.feeGeneration.batchCharges() },
        { label: 'Đẩy DNG hàng loạt', href: financeRoutes.collect.dngWorklist() },
    ],
    late: [
        { label: 'Nhắc nợ', href: financeRoutes.collect.dueReminders() },
        { label: 'Đối soát', href: financeRoutes.collect.settlement() },
    ],
    mid: [
        { label: 'Phân bổ', href: financeRoutes.collect.settlement() },
        { label: 'Nhắc nợ', href: financeRoutes.collect.dueReminders() },
    ],
} as const;

const setPhase = (key: 'early' | 'mid' | 'late'): void => {
    router.post(financeRoutes.cockpit.phase(), { phase: key }, { preserveScroll: true });
};
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-1">
            <CardTitle class="text-sm">Theo giai đoạn <Badge variant="secondary">{{ phase.label }}</Badge></CardTitle>
        </CardHeader>
        <CardContent class="space-y-2">
            <Button v-for="s in shortcuts[phase.key]" :key="s.label" as-child size="sm" variant="outline" class="w-full justify-start">
                <Link :href="s.href">{{ s.label }}</Link>
            </Button>
            <div class="flex gap-1 pt-1">
                <Button v-for="k in (['early', 'mid', 'late'] as const)" :key="k" size="sm"
                    :variant="phase.key === k ? 'default' : 'ghost'" @click="setPhase(k)">{{ k }}</Button>
            </div>
        </CardContent>
    </Card>
</template>
```

- [ ] **Step 6: The Cockpit page** — `resources/js/pages/Finance/Cockpit/Index.vue` (layout §4.1: banner → KPI ribbon → 2/3 queues + 1/3 right column; `usePoll` + manual refresh):

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import StatsCard from '@/components/StatsCard.vue';
import CriticalBanner from '@/components/finance/cockpit/CriticalBanner.vue';
import QueueCard from '@/components/finance/cockpit/QueueCard.vue';
import DataHealthPanel from '@/components/finance/cockpit/DataHealthPanel.vue';
import PhaseShortcuts from '@/components/finance/cockpit/PhaseShortcuts.vue';
import ActionPanel from '@/components/finance/cockpit/ActionPanel.vue';
import type { CockpitDataHealth, CockpitKpi, CockpitPhase, CockpitQueue } from '@/types/finance';
import { formatCurrency } from '@/utils/format';
import { Head, router, usePoll } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    kpi: CockpitKpi;
    queues: CockpitQueue[];
    phase: CockpitPhase;
    data_health?: CockpitDataHealth;
}>();

// Poll only the light queue/kpi props; keepAlive defaults false → 90% background throttle.
usePoll(90000, { only: ['kpi', 'queues', 'phase'] });

const refreshing = ref(false);
const refreshAll = (): void => {
    refreshing.value = true;
    router.reload({ only: ['kpi', 'queues', 'phase', 'data_health'], onFinish: () => (refreshing.value = false) });
};

const activeQueue = ref<CockpitQueue | null>(null);
const openQueue = (q: CockpitQueue): void => { activeQueue.value = q; };
</script>

<template>
    <Head title="Finance · Hôm nay" />
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold tracking-tight">Hôm nay</h1>
            <Button size="sm" variant="outline" :disabled="refreshing" @click="refreshAll">
                <RefreshCw class="mr-1 size-4" :class="{ 'animate-spin': refreshing }" /> Làm mới
            </Button>
        </div>

        <CriticalBanner :critical-count="props.data_health?.critical_count ?? 0" />

        <!-- KPI ribbon -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <StatsCard title="Tổng phải thu" :value="formatCurrency(props.kpi.total_receivable)" />
            <StatsCard title="Đã thu" :value="`${props.kpi.collected_pct}%`" :sub-items="[{ label: 'Số tiền', value: formatCurrency(props.kpi.total_collected) }]" />
            <StatsCard title="SV chưa sinh phí" :value="props.kpi.uncharged_count" />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Cần xử lý (2/3) -->
            <section class="space-y-3 lg:col-span-2">
                <h2 class="text-muted-foreground text-sm font-semibold uppercase">Cần xử lý</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <QueueCard v-for="q in props.queues" :key="q.key" :queue="q" @open="openQueue" />
                </div>
            </section>
            <!-- Right column (1/3) -->
            <aside class="space-y-3">
                <DataHealthPanel :data-health="props.data_health" />
                <PhaseShortcuts :phase="props.phase" />
            </aside>
        </div>

        <ActionPanel :queue="activeQueue" @close="activeQueue = null" />
    </div>
</template>
```

> `ActionPanel.vue` is created in Task 6; import it now so the page compiles (or temporarily stub it). Confirm the page's layout attachment matches sibling finance pages (same as the M1/M2 note).

- [ ] **Step 7: Lint + commit**

Run: `./scripts/dev.sh npm run lint -- resources/js/types/finance.ts resources/js/components/finance/cockpit/ resources/js/pages/Finance/Cockpit/Index.vue`

```bash
git add resources/js/types/finance.ts resources/js/components/finance/cockpit/ resources/js/pages/Finance/Cockpit/Index.vue
git commit -m "feat(finance): add cockpit page with banner, KPI, queues, data-health, phase"
```

---

## Task 6: Frontend — Action Panel slideover + IA "Hôm nay" repoint

**Files:**
- Create: `resources/js/components/finance/cockpit/ActionPanel.vue`
- Modify: `resources/js/constants/menu-sidebar.ts`

- [ ] **Step 1: Action Panel** — `resources/js/components/finance/cockpit/ActionPanel.vue` (§4.3: open → fetch top-N rows → quick action → stay in queue; `[Mở hồ sơ SV ↗]`):

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApiRequest';
import { financeRoutes } from '@/utils/routes';
import type { CockpitQueue, CockpitQueueRow } from '@/types/finance';
import { Link, router } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ queue: CockpitQueue | null }>();
const emit = defineEmits<{ (e: 'close'): void }>();

const open = computed({ get: () => props.queue !== null, set: (v) => { if (!v) emit('close'); } });
const rows = ref<CockpitQueueRow[]>([]);
const loading = ref(false);
const { get } = useApi();

watch(
    () => props.queue,
    async (queue) => {
        if (!queue) return;
        // Queues without panel rows deep-link to their worklist page instead.
        const hasРanelRows = ['webhook_errors', 'installment_failures'].includes(queue.key);
        if (!hasРanelRows) {
            router.visit(queue.action_url);
            emit('close');
            return;
        }
        loading.value = true;
        try {
            const res = await get<{ rows: CockpitQueueRow[] }>(financeRoutes.cockpit.queueRows(queue.key));
            rows.value = res.success && res.data ? res.data.rows : [];
        } finally {
            loading.value = false;
        }
    },
);

const runAction = (row: CockpitQueueRow): void => {
    router.post(row.primary_action.url, {}, {
        preserveScroll: true,
        only: ['kpi', 'queues'],
        onSuccess: () => { rows.value = rows.value.filter((r) => r.id !== row.id); }, // stay in queue
    });
};
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="w-full overflow-y-auto p-4 sm:max-w-lg">
            <SheetHeader><SheetTitle>{{ queue?.label }}</SheetTitle></SheetHeader>
            <Skeleton v-if="loading" class="mt-4 h-32 w-full" />
            <div v-else class="mt-4 space-y-2">
                <div v-for="row in rows" :key="row.id" class="rounded-md border p-2 text-sm">
                    <p class="font-medium">{{ row.title }}</p>
                    <p class="text-muted-foreground text-xs">{{ row.subtitle }} · {{ row.age }}</p>
                    <div class="mt-2 flex items-center gap-2">
                        <Button size="sm" variant="outline" @click="runAction(row)">{{ row.primary_action.label }}</Button>
                        <Button v-if="row.student_id" as-child size="sm" variant="ghost">
                            <Link :href="financeRoutes.students.overview(row.student_id)"><ExternalLink class="mr-1 size-4" /> Mở hồ sơ SV</Link>
                        </Button>
                    </div>
                </div>
                <p v-if="rows.length === 0" class="text-muted-foreground text-sm">Hàng đợi trống.</p>
            </div>
        </SheetContent>
    </Sheet>
</template>
```

> Fix the typo'd identifier `hasРanelRows` (it contains a Cyrillic "Р") to `hasPanelRows` when implementing — written here only to flag that copy-paste of mixed-script identifiers breaks lint. Use plain ASCII.

- [ ] **Step 2: Repoint the "Hôm nay" sidebar item** — in `resources/js/constants/menu-sidebar.ts`, change the "Hôm nay" item's `href` from `financeRoutes.today.dashboard()` to `financeRoutes.cockpit.index()` and its permission from `view_finance_operations_dashboard` to `view_finance_cockpit`:

```typescript
            {
                title: 'Hôm nay',
                href: financeRoutes.cockpit.index(),
                icon: LayoutDashboard,
                requiredPermissions: ['view_finance_cockpit'],
            },
```

(Keep the old Billing Dashboard reachable: add it under "Tra cứu & Audit" or leave it accessible by route. Decide and note — the design replaces the dashboard with the cockpit as the landing screen, so demoting the old dashboard to a secondary lookup entry is acceptable.)

- [ ] **Step 3: Lint + commit**

Run: `./scripts/dev.sh npm run lint -- resources/js/components/finance/cockpit/ActionPanel.vue resources/js/constants/menu-sidebar.ts`

```bash
git add resources/js/components/finance/cockpit/ActionPanel.vue resources/js/constants/menu-sidebar.ts
git commit -m "feat(finance): add cockpit action panel and make Hôm nay land on the cockpit"
```

---

## Task 7: Integration smoke, permission matrix, invariant cross-check, harness trace

**Files:** `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md`

- [ ] **Step 1: Full M3 backend suite**

Run: `./scripts/dev.sh test tests/Feature/Finance/Cockpit tests/Feature/Finance/Audit/InvariantDrilldownTest.php`
Re-run the audit page suite to confirm the request-rule + controller extension didn't regress it: `./scripts/dev.sh test tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php`.
Expected: all PASS.

- [ ] **Step 2: Build + browser smoke (§8 permission matrix + §4 behavior)**

Run: `./scripts/dev.sh npm run build`. Then verify on `/finance/cockpit`:
1. `view_finance_cockpit` only → page loads; queue cards whose `permission` the user lacks are hidden (filter cards by `usePermissions().can(queue.permission)` in `QueueCard` parent — add this guard if not already, mirroring the sidebar filter).
2. Queues poll (~90s) without spamming; "Làm mới" reloads all incl. `data_health`.
3. Changing the topbar semester (M1) updates `dng_due`/`lifecycle`/`charge_errors` counts but NOT `webhook_errors`/`unallocated` (badge "Toàn campus").
4. `data_health` resolves after a skeleton; an invariant tile links to `/finance/audit?finding_code=INV-X&scope=campus` and lands on a resolved target (or the webhook list for INV-11).
5. CRITICAL banner shows only when `critical_count > 0`.
6. Opening the webhook queue panel → top rows → Retry removes the row and refreshes counts.
7. Phase shortcuts reflect the inferred phase; manual switch persists.
Record pass/fail per row.

- [ ] **Step 3: Cross-check invariant counts**

Run `./scripts/dev.sh artisan finance:audit-invariants` (confirm exact command name) and compare its per-code counts to the cockpit data-health tiles for the same scope. They should match (the cockpit reuses the same `FinanceIntegrityAuditor`). Capture into validation evidence.

- [ ] **Step 4: Update story validation doc** — append a "Milestone 3 — Cockpit" section: routes + gates, the queue→permission→scope-badge table, the invariant drilldown mapping table (§4.4), `usePoll` config, the deferred-data-health perf note, test names, smoke results, invariant cross-check.

- [ ] **Step 5: Commit + harness trace**

```bash
git add docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/validation.md
git commit -m "docs(finance): record cockpit M3 acceptance evidence"
```

```bash
./scripts/harness trace --summary "S-010 Milestone 3: Cockpit Hôm nay (queues, data-health, invariant drilldown, phase)" --story FIN-REV-010 --actions "view_finance_cockpit, cockpit overview + queue counts + queue rows, deferred data-health (15 invariants + balance match), invariant drilldown contract on audit workspace, usePoll, IA Hôm nay repoint" --changed "app/Modules/Finance/Queries/Cockpit, app/Modules/Finance/Support/Integrity, app/Modules/Finance/Http/Web/Admin, resources/js/pages/Finance/Cockpit, resources/js/components/finance/cockpit" --outcome completed --friction "campus-scoped invariant counts heavy → data-health deferred not polled; logged volume-optimization backlog item"
```

- [ ] **Step 6: Log the perf backlog item**

```bash
./scripts/harness backlog add --title "Push campus-scoped cache-drift + invariant counts into SQL (avoid PHP per-invoice iteration in cockpit data-health)" --while "FIN-REV-010" --risk normal
```

---

## Self-Review (against design §4, §3.1, §8, §10 M3)

**1. Spec coverage:**
- §4.1 layering: CRITICAL banner → KPI ribbon → 2/3 queues + 1/3 right column — Task 5 page. ✅
- §4.2 six queues with fixed anatomy (title+icon+count+severity dot, 1-line breakdown, oldest-age, one action) — Task 2 counts + Task 5 `QueueCard` (oldest-age available via `oldest_at`/`age`). ✅
- §4.3 "click 1 việc" → Action Panel slideover, resolve in place + stay, `[Mở 360 ↗]` — Task 6 `ActionPanel`. ✅
- §4.4 15 invariants as trust signals + "Số dư khớp" (both cached_paid AND cached_total via `snapshotDriftsFromCache`; denominator in tooltip) + one-click drilldown — Task 3 + Task 5 `DataHealthPanel`. ✅
- §4.4 drilldown contract (extend Audit with finding_code/scope/sample_id + sample-type→target mapping + campus lock + list-view fallback for un-graphable samples) — **Task 4, owned by M3** per §10. ✅
- §4.5 phase inference + manual switch — Task 2 `FinanceCollectionPhase` + Task 5 `PhaseShortcuts`. ✅
- §3.1 semester scope: webhook/unallocated `obeys_semester:false` (never hidden by semester change); due/lifecycle/charge-errors `obeys_semester:true`; per-queue scope badge — Task 2 + test. ✅
- §8 `view_finance_cockpit` separate permission; each widget checks its source permission — Task 1 + Task 2 per-queue `permission` + Task 6 render guard. ✅
- §8 `usePoll` ~60–120s + background throttle (keepAlive default) + manual refresh + light count queries — Task 5 (`usePoll(90000, { only })`) + Task 2 (count/aggregate only). ✅
- §8 campus lock ("toàn hệ thống" = current campus unless all-campus) — Task 3 scope resolution + Task 4 drilldown scope. ✅

**2. Placeholder scan:** No TBD/TODO. Two deliberately-flagged items: the Task 4 controller integration test ships with a `skip` **only as a template** with an explicit instruction to replace it (and a cheaper unit-level alternative) — the instruction says "Do not ship the `skip`"; and a Cyrillic-character typo in `ActionPanel` is flagged to fix. Both are called out, not hidden.

**3. Type/name consistency:** Queue keys (`webhook_errors`/`unallocated`/`dng_due`/`lifecycle`/`charge_errors`/`installment_failures`) consistent across the overview query, the rows query, the page, and the action panel. Invariant codes + sample-id-types in `FinanceInvariantSampleResolver` match the registry mapping verified by exploration. Route names (`finance.cockpit.index/.queue-rows/.phase`) consistent across `FINANCE_ROUTE_NAMES`, `financeRoutes.cockpit.*`, `routes/web.php`, controller. `usePoll` import + signature match the Inertia v3 doc. Data-health prop shape (`{scope_badge, critical_count, invariants[], balance_match{match_pct,matched,denominator}}`) matches the TS `CockpitDataHealth`.

**In-step confirmations (each has a fallback):** `payment_applications` table/column for the unallocated subquery; `FinanceCharge.semester_id` for the installment filter; `FinanceInvariantRegistry` accessor (`find(code)` / `all()`) + `FinanceInvariant` accessors; `InvoiceDiscount` FQCN + the `dng_payment_request_charges` pivot column; `DngWebhookEvent::create` fillable; the exact `finance:audit-invariants` command; `Inertia::location` vs `redirect()` for the no-target drilldown.

**Flagged for human:** (1) campus-scoped invariant/drift counts iterate in PHP — deferred + manual-refresh for M3; SQL-pushdown is a logged backlog item gated by the §11 volume question. (2) Demoting the old Billing Dashboard when "Hôm nay" repoints to the cockpit — keep it reachable as a secondary lookup entry (confirm with the user whether to retire it entirely).

---

## Execution Handoff

Milestone 3 plan saved to `docs/superpowers/plans/2026-06-15-finance-office-cockpit.md`. Recommended flow (AGENTS.md): **evaluate with `executing-plans`** → human approval → **subagent-driven-development**. M1 + M2 must be merged first (the cockpit repoints the M1 "Hôm nay" item and reuses M2 patterns).

Options: (1) Evaluate first (executing-plans), (2) Subagent-Driven execution, (3) Inline execution. Which would you like — and shall I continue with **Milestone 4 (Batch Studio)** next? (M4 is the preview-token-contract milestone; I'd want an exploration pass on `GenerateBatchChargesAction`/`GenerateMajorChargesAction`, the existing preview queries/exports, and `CreateBatchDngFromChargesAction` before writing it.)
