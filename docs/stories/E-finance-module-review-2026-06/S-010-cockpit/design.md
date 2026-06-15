# Design

## Domain Model

Milestone 3 is a **read-only aggregation layer** over existing summary queries and
the integrity registry. New backend objects assemble counts and expose drilldown contracts;
Action Panel writes delegate to existing routes.

New read models:

- **`GetFinanceCockpitOverviewQuery`** — KPI ribbon + 6 queue counts + collection phase.
- **`GetFinanceCockpitDataHealthQuery`** — 15 invariants + "Số dư khớp" + critical banner
  source (campus- or all-campus-scoped).
- **`GetFinanceCockpitQueueRowsQuery`** — top-N rows for one queue (Action Panel JSON).
- **`GetInstallmentPushFailureCountQuery`** — missing queue count (`pending` + `last_push_error`).
- **`FinanceCollectionPhase`** — early/mid/late from semester + active billing cycle dates.
- **`FinanceInvariantSampleResolver`** — invariant code → audit `target_type` or list-view URL.

Business rules (non-negotiable):

- **`view_finance_cockpit`** gates the page; each queue/widget still checks its **source
  permission** at render.
- **Campus lock:** "toàn hệ thống" = current campus unless `view_finance_all_campus`.
- **Semester scope (§3.1):** `webhook_errors` and `unallocated` are semester-agnostic
  (`obeys_semester: false`); `dng_due`, `lifecycle`, `charge_errors` obey semester change.
- **Polling:** light props (`kpi`, `queues`, `phase`) via `usePoll` (~90s, background-throttled);
  `data_health` is **deferred**, manually refreshed only.
- **"Số dư khớp"** uses `SettlementService::snapshotDriftsFromCache` exactly (both
  `cached_paid` and `cached_total` vs snapshot).
- **No money math in Vue** — all amounts and counts from existing queries/auditor.

## Application Flow

### Cockpit page (read path)

```
GET /finance/cockpit
  → FinanceCockpitController::index
  → GetFinanceCockpitOverviewQuery (sync: kpi, queues, phase)
  → Inertia::defer(GetFinanceCockpitDataHealthQuery) (data_health)
  → Finance/Cockpit/Index.vue
  → usePoll(90000, { only: ['kpi','queues','phase'] })
  → manual refresh reloads all incl. data_health
```

### Action Panel (read + existing writes)

```
QueueCard "Xử lý"
  → if queue has panel rows: GET /finance/cockpit/queue-rows?queue=...
  → ActionPanel lists top-N rows
  → primary_action POST (webhook retry, etc.) → preserveScroll, only kpi+queues
  → else: router.visit(queue.action_url)
```

### Invariant drilldown (M3-owned cross-dependency)

```
DataHealthPanel tile click
  → /finance/audit?finding_code=INV-X&scope=campus[&sample_id=...]
  → FinanceAuditSearchRequest validates new params
  → FinanceInvariantSampleResolver maps code → target_type / list URL
  → FinanceAuditWorkspaceController resolves sample → graph target or redirect
```

## Interface Contract

### Web (Inertia)

| Route name | Permission | Handler |
| --- | --- | --- |
| `finance.cockpit.index` | `view_finance_cockpit` | `FinanceCockpitController::index` |
| `finance.cockpit.queue-rows` | `view_finance_cockpit` + queue source permission | `FinanceCockpitController::queueRows` (JSON) |
| `finance.cockpit.phase` | `view_finance_cockpit` | Phase override POST (if implemented) |

### Queue keys (stable across backend + frontend)

| Key | Semester-bound | Scope badge | Source permission (representative) |
| --- | --- | --- | --- |
| `webhook_errors` | no | campus | DNG webhook view permission |
| `unallocated` | no | campus | settlement worklist permission |
| `dng_due` | yes | semester | due-items permission |
| `lifecycle` | yes | semester | lifecycle exceptions permission |
| `charge_errors` | yes | semester | billing exceptions permission |
| `installment_failures` | yes | multi | charge/installment permission |

### Invariant drilldown params (Audit extension)

| Param | Purpose |
| --- | --- |
| `finding_code` | INV-1 … INV-15 |
| `scope` | `campus` \| `all_campus` (locked to user capability) |
| `sample_id` | optional concrete violating record id |

`FinanceInvariantSampleResolver` maps sample id types (payment.id, charge.id, etc.) to
audit `target_type` or `listUrlFor()` for un-graphable codes (e.g. INV-11 webhooks).

### Frontend types (`resources/js/types/finance.ts`)

- `CockpitKpi`, `CockpitQueue`, `CockpitPhase`, `CockpitInvariant`, `CockpitDataHealth`,
  `CockpitQueueRow` — snake_case props from Laravel.

## UI Surfaces

| Component | Responsibility |
| --- | --- |
| `Finance/Cockpit/Index.vue` | Page shell, polling, manual refresh, layout §4.1 |
| `CriticalBanner.vue` | CRITICAL count + link to audit |
| `QueueCard.vue` | Queue anatomy: severity dot, count, scope badge, open action |
| `DataHealthPanel.vue` | Deferred skeleton, invariant tiles, balance match, drilldown links |
| `PhaseShortcuts.vue` | Inferred phase + manual override |
| `ActionPanel.vue` | Sheet slideover, row actions, Student 360 link |

Reuse: `StatsCard.vue`, `@/components/ui/sheet`, `lucide-vue-next`, `financeRoutes` helpers.

## Performance Notes

Campus-scoped invariant counts and cache-drift iteration can be heavy in PHP. M3 mitigates
by deferring `data_health` and excluding it from `usePoll`. Log backlog item for SQL pushdown
if volume warrants.