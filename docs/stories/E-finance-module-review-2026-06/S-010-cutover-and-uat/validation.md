# Validation

## Proof Strategy

M6 validates the redesigned Finance Office as a complete operator experience.
The proof must cover both machine-checkable contracts and manual UAT:

- Navigation sends normal operators to M1-M5 destinations.
- Legacy entrypoints have explicit cutover decisions.
- Permission gates remain aligned across menu, route, and visible actions.
- UAT covers the core daily Finance workflows.
- No money-write behavior changes as a side effect of cutover.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Route helper/decision helpers if introduced. |
| Integration | Route redirects, permission gates, legacy URL behavior, route-list assertions for kept deep links. |
| E2E | Operator UAT: Cockpit, Student 360, Batch Studio, DNG/settlement, lifecycle exceptions, Lookup & Audit. |
| Platform | Targeted eslint/prettier for touched Vue/TS/docs; route-list command for Finance routes. |
| Logs/Audit | `finance:audit-invariants` sanity if any write-capable route/controller is touched. |

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php
./scripts/dev.sh test tests/Feature/Finance/Search tests/Feature/Finance/Shell tests/Feature/Finance/Student360
./scripts/dev.sh test tests/Feature/Finance/Cockpit tests/Feature/Finance/Batch tests/Feature/Finance/Lookup
./scripts/dev.sh test tests/Feature/Finance/Audit/InvariantDrilldownTest.php tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php
./scripts/dev.sh artisan route:list --path=finance
./scripts/dev.sh npm exec eslint resources/js/constants/menu-sidebar.ts resources/js/constants/finance-routes.ts resources/js/utils/routes.ts resources/js/pages/Finance/Operations/Settlement.vue resources/js/pages/Finance/Payments/AutoAllocate.vue
```

`finance:audit-invariants` not required — M6 is navigation-only (no write-capable route/controller changes).

## UAT Checklist

### Roles

- [ ] Finance staff (`can_bo`) sees the task-first Finance Office navigation.
- [ ] Finance lead (`truong_phong`) sees review/oversight paths appropriate to
      current permissions.
- [ ] Restricted finance user cannot see actions without permission.
- [ ] All-campus user sees correct scope labels where applicable.

### Workflows

- [ ] Start at **Hôm nay** and triage queues from Cockpit.
- [ ] Search a student globally and land on Student 360 with focus behavior.
- [ ] Run charge-generation preview path in Batch Studio.
- [ ] Run DNG push preview path in Batch Studio.
- [ ] Review reminders path and double-send guard messaging.
- [ ] Open settlement/allocation path from queue or Student 360.
- [ ] Review lifecycle/billing exceptions.
- [ ] Use Charge/Invoice/Payment lookup and row→360 links.
- [ ] Open Audit Workspace from invariant or lookup context.

## Acceptance Evidence

### Milestone 6 — Cutover & UAT

Scope: route/menu/page inventory, legacy entrypoint decisions, navigation cutover,
permission pass, operator UAT evidence. Navigation-only — no money-write logic.

**Portal impact: none** (admin/staff Inertia UI only).

_Status: implemented — automated evidence recorded 2026-06-16._

Cutover inventory: [`cutover-inventory.md`](./cutover-inventory.md)

#### Automated acceptance

- [x] `./scripts/dev.sh test tests/Feature/Finance/Cutover/FinanceOfficeCutoverTest.php` — **5 passed** (40 assertions)
- [x] `./scripts/dev.sh test tests/Feature/Finance/Search tests/Feature/Finance/Shell tests/Feature/Finance/Student360` — **21 passed**
- [x] `./scripts/dev.sh test tests/Feature/Finance/Cockpit tests/Feature/Finance/Batch tests/Feature/Finance/Lookup` — **41 passed**
- [x] `./scripts/dev.sh test tests/Feature/Finance/Audit/InvariantDrilldownTest.php tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php` — **5 passed**
- [x] **Total regression suite: 67 passed, 329 assertions**
- [x] Targeted eslint on changed frontend files — **0 errors**
- [x] `./scripts/dev.sh artisan route:list --path=finance` — primary + deep-link routes confirmed
- [x] `finance:audit-invariants` — **not run** (no write-capable paths touched)

#### Navigation cutover applied

| Change | Decision |
| --- | --- |
| Hôm nay → Cockpit | **keep** (unchanged from M3) |
| Batch Charges (All Students) removed from sidebar | **hide** — Batch Studio is primary bulk path |
| Billing Dashboard renamed "Billing KPIs (Legacy)" under Tra cứu & Audit | **deep-link** |
| Settlement back button → Cockpit when permitted | Internal link cutover |
| Auto-allocate completion CTA → Cockpit ("Về Hôm nay") | Internal link cutover |

#### Permission matrix (server-side contracts)

| Surface | Permission | Notes |
| --- | --- | --- |
| Cockpit (Hôm nay) | `view_finance_cockpit` | Primary landing |
| Batch Studio hub | `view_finance_batch_studio` | Bulk wizard |
| Billing KPIs (Legacy) | `view_finance_operations_dashboard` | Secondary; not redirected at route level |
| Student 360 | `view_finance_student_overview` | Not in sidebar; ⌘K + row clicks |
| Lookup pages | `view_finance_charges` / `view_finance_invoices` / `view_finance_payments` | M5 standard |
| Audit Workspace | `view_finance_audit_workspace` | M5 graph |

#### Browser smoke (manual — not run in CI)

Manual UAT checklist above remains for operator walkthrough. Server-side contracts
for each workflow are covered by the 67-test regression suite.