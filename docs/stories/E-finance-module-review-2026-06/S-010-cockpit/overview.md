# Overview

## Current Behavior

The Finance Office "Hôm nay" sidebar item lands on the **Billing Dashboard**
(`/finance/today` or equivalent operations dashboard route) with `view_finance_operations_dashboard`.
There is no dedicated triage screen that surfaces:

- A CRITICAL money-integrity banner from invariant findings
- A compact KPI ribbon (receivable, collected %, uncharged count)
- Priority "Cần xử lý" queues with in-place resolution
- A "Sức khỏe dữ liệu" trust panel (15 invariants + "Số dư khớp")
- Adaptive "Theo giai đoạn" shortcuts for the current collection phase

The Audit Workspace accepts universal search params but does not yet support
`finding_code` / `scope` / `sample_id` drilldown from invariant tiles.

Existing summary queries (`GetDueItemsSummaryQuery`, `GetBillingExceptionCountsQuery`,
`ListDngWebhookEventsQuery`, `ListSettlementWorklistQuery`, `GetBillingDashboardStatsQuery`,
etc.) and `FinanceIntegrityAuditor` exist but are not assembled into a cockpit read model.

## Target Behavior

Build the **Cockpit "Hôm nay"** triage screen at `GET /finance/cockpit` — a
read-only aggregation surface that helps finance staff start their day:

- **CRITICAL banner** when `critical_count > 0` (links to Audit Workspace)
- **KPI ribbon** — total receivable, collected %, uncharged count from existing stats
- **Six priority queues** with counts, severity dots, scope badges, and semester semantics:
  webhook errors, unallocated payments, DNG due, lifecycle exceptions, charge errors,
  installment push failures
- **Data-health panel** — 15 invariant counts + "Số dư khớp" ratio; deferred prop,
  manually refreshed (not polled)
- **Phase shortcuts** — early/mid/late collection phase from semester + billing cycle dates
- **Action Panel** — `Sheet` slideover fetching top-N rows on demand; reuses existing
  write routes (webhook retry, acknowledge, allocate) for in-place resolution

**Invariant drilldown contract (M3-owned):** extend `FinanceAuditSearchRequest` and
`FinanceAuditWorkspaceController` with `finding_code` / `scope` / `sample_id` plus
`FinanceInvariantSampleResolver` mapping invariant samples to audit targets (or list-view
fallback for un-graphable samples).

**Read-only milestone:** no new money math; Action Panel writes reuse already-tested routes.

This packet is the canonical Milestone 3 story for
`docs/superpowers/plans/2026-06-15-finance-office-cockpit.md`.

## Plan Task Mapping

| Plan task | Included in this story |
| --- | --- |
| Task 1: `view_finance_cockpit` permission | Thin page gate + seeder |
| Task 2: Cockpit overview + queue rows | KPI ribbon, 6 queue counts, phase, JSON queue-rows endpoint |
| Task 3: Data-health query | 15 invariants + balance match + critical banner source (deferred) |
| Task 4: Invariant drilldown contract | `FinanceInvariantSampleResolver` + Audit request/controller extension |
| Task 5: Cockpit page + polling | `Index.vue`, banner/KPI/queues/data-health/phase components, `usePoll` |
| Task 6: Action Panel + IA repoint | `ActionPanel.vue` slideover; sidebar "Hôm nay" → cockpit |
| Task 7: Integration smoke + evidence | Cockpit + drilldown test suite, browser smoke, invariant cross-check |

## Affected Users

- Finance staff who need a daily triage landing screen ("Hôm nay").
- Finance leads who monitor data-health invariants and cache drift before operations.
- Auditors who drill from invariant tiles into the Audit Workspace.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md` (§3.1 semester scope,
  §4 Cockpit, §8 permissions/polling, §10 milestone 3)
- `docs/superpowers/plans/2026-06-15-finance-office-cockpit.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`
- `docs/stories/E-finance-module-review-2026-06/S-010-student-360-full/`

## Portal Impact

Portal impact: none.

This story targets admin/staff Inertia UI and Finance web routes. It does not
change `/api/v1/student/*` or `/api/v1/lecturer/*`.

## Non-Goals

- No new money math, ledger tables, or write Actions in the Cockpit read path.
- Do not replace existing worklist/detail pages — Action Panel deep-links where
  in-place resolution is not supported.
- Do not poll the heavy `data_health` prop (deferred + manual refresh only).
- Do not implement M5 lookup standard or M4 Batch Studio (may receive hand-offs later).
- Do not retire the Billing Dashboard entirely without product confirmation — demote
  to secondary lookup entry when "Hôm nay" repoints to cockpit.

## Dependencies

| Dependency | Required for | Blocks cockpit work? |
| --- | --- | --- |
| M1 `FIN-REV-010-finance-staff-workspace` | `financeRoutes`, semester prop, sidebar IA, `usePoll` foundation | Yes — must be merged |
| M2 `FIN-REV-010-student-360-full` | `Sheet` drawers, 4-layer destructive UI patterns, Student 360 links | Yes — Action Panel reuses M2 patterns |
| M5 `FIN-REV-010-lookup-and-audit` | Audit Workspace as drilldown destination | No — M3 extends Audit with drilldown params |
| `FinanceIntegrityAuditor` + registry | Data-health panel | No — already exists |

## Unresolved Questions (confirm at implementation)

- **Billing Dashboard demotion:** keep reachable under "Tra cứu & Audit" vs hidden route only.
- **Campus-scoped invariant/drift counts:** PHP iteration is heavy — deferred prop + backlog
  item for SQL pushdown (see plan §11).
- **Partial-reload test plumbing:** prefer unit-testing `GetFinanceCockpitDataHealthQuery` if
  Inertia partial headers are brittle in Pest.
- **Queue render guard:** filter queue cards by `usePermissions().can(queue.permission)` in
  parent if not already in plan components.