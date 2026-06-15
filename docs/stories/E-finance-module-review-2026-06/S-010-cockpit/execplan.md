# Exec Plan

## Goal

Implement and accept Milestone 3 from
`docs/superpowers/plans/2026-06-15-finance-office-cockpit.md` as one consolidated
Harness story.

Deliver the Cockpit "Hôm nay" triage screen: CRITICAL banner, KPI ribbon, six
priority queues with Action Panel, deferred data-health panel (15 invariants +
balance match), phase shortcuts, invariant drilldown into Audit Workspace, and
sidebar "Hôm nay" repoint. Read-only aggregation — Action Panel reuses existing
write routes only.

## Scope

In scope:

- `view_finance_cockpit` permission + seeder (Task 1).
- `GetInstallmentPushFailureCountQuery`, `FinanceCollectionPhase`,
  `GetFinanceCockpitOverviewQuery`, `GetFinanceCockpitQueueRowsQuery`,
  `FinanceCockpitController`, routes + route constants (Task 2).
- `GetFinanceCockpitDataHealthQuery` + deferred `data_health` prop (Task 3).
- `FinanceInvariantSampleResolver` + Audit request/controller extension (Task 4).
- Cockpit Vue page + components + `usePoll` + types (Task 5).
- `ActionPanel.vue` + sidebar "Hôm nay" repoint (Task 6).
- Integration tests, browser smoke, invariant cross-check, validation evidence,
  harness trace + perf backlog item (Task 7).

Out of scope:

- New money math, ledger schema, or bespoke write Actions.
- M4 Batch Studio, M5 lookup standard implementation.
- Polling `data_health` (explicitly deferred + manual refresh).
- Student/lecturer portal API changes.
- Full retirement of Billing Dashboard without product sign-off.

## Risk Classification

Risk flags:

- `authz` — page permission vs per-queue source permissions.
- `read-model` — assembling multiple summary queries with correct campus/semester scope.
- Campus-scope correctness on invariants and balance-match denominator.

Hard gates:

- `view_finance_cockpit` required; widgets hidden without source permission.
- Campus lock on scope badges and drilldown (`all_campus` only with permission).
- Light polled path uses counts only — no full-row pulls in overview query.
- Invariant drilldown must not weaken Audit authorization.
- Action Panel writes must use existing tested routes (webhook retry, acknowledge, allocate).

## Work Phases

1. **Permission** — `view_finance_cockpit` declare + seed (Task 1).
2. **Overview backend** — installment count, phase, overview + queue-rows queries,
   controller, routes, overview tests (Task 2).
3. **Data-health backend** — deferred invariants + balance match query + tests (Task 3).
4. **Drilldown contract** — sample resolver + Audit extension + tests (Task 4).
5. **Cockpit frontend** — types, components, page, polling (Task 5).
6. **Action Panel + nav** — slideover + sidebar repoint (Task 6).
7. **Acceptance** — full Cockpit suite, audit regression, build, browser smoke,
   `finance:audit-invariants` cross-check, evidence in `validation.md` (Task 7).

## Stop Conditions

Pause for human confirmation if:

- Extracted queue counts diverge from source worklist pages for the same scope.
- Invariant tile counts disagree with `finance:audit-invariants` for the same scope.
- A queue action requires a new write route not already covered by M1/M2/M5.
- Billing Dashboard demotion conflicts with an active operator workflow.
- `data_health` load time blocks acceptable UX even with deferral (escalate SQL pushdown).

## Implementation Source

Execute task-by-task from:

`docs/superpowers/plans/2026-06-15-finance-office-cockpit.md`

Recommended execution mode: subagent-driven development (one task per subagent
with review between tasks) or inline execution with checkpoints.

**Prerequisite:** M1 (`FIN-REV-010-finance-staff-workspace`) and M2
(`FIN-REV-010-student-360-full`) must be merged before starting M3.