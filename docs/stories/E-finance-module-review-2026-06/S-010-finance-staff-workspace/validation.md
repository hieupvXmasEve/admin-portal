# Validation

## Proof Strategy

This story is not implemented yet. Validation for this requirements story is
limited to documentation consistency and future proof planning.

When implementation begins, validation must prove that the staff workspace makes
existing Finance work easier without changing money truth or bypassing current
guards.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Future task-summary query class returns checklist counts from existing sources without new money math. |
| Integration | Future workspace route is permission-scoped and campus-scoped. |
| Integration | Future proactive charge drawer reuses existing charge generation/actions and preserves existing validation. |
| Integration | Future DNG drawer reuses existing DNG worklist action and preserves DNG linkage/audit behavior. |
| E2E | Staff selects semester, opens checklist item, previews, confirms, sees result, and can open source page. |
| Platform | Finance Office menu makes staff workspace the operational entry while source pages remain reachable. |
| Performance | Checklist counts stay bounded by semester/campus and avoid loading all ledger rows. |
| Logs/Audit | Future write drawers preserve existing finance/DNG audit evidence. |

## Fixtures

Future implementation should use deterministic fixtures for:

- Semester with major/EGC students.
- Non-academic BHYT charge creation.
- One-student manual charge creation.
- DNG-eligible students with and without active DNG.
- Retake registration needing charge creation.
- Academic defer case with preserve/forfeit choice.
- Lifecycle exception requiring keep-debt/cancel/void decision.

## Commands

Commands will be added after an implementation plan exists.

```text
Not applicable yet. This story currently records product/workflow intent only.
```

## Acceptance Evidence

Current documentation evidence:

- Story created separately from S-009 so audit and staff operations do not become
  one overloaded feature.
- Confirmed product choices are recorded in overview/design/execplan.

## Milestone 1 — Shell + 360 foundation (implemented 2026-06-15)

Scope: finance-aware app shell (global ⌘K search + semester switcher), work-organized
5-group sidebar, finance route constants, minimal Student 360 route shell. Read-only +
presentation only — no money-write logic touched.

**Portal impact: none** (admin/staff web only; no change to `/api/v1/student/*` or
`/api/v1/lecturer/*`). **No money state changed** in Milestone 1 (read-model + session +
presentation only), so no `finance:audit-invariants` evidence is required.

### Menu migration (Task 6)

| Current item | Permission | New group | Action |
| --- | --- | --- | --- |
| Audit Workspace `/finance/audit` | `view_finance_audit_workspace` | 🔎 Tra cứu & Audit | moved → `financeRoutes.audit()` |
| Billing Dashboard `/finance/operations/dashboard` | `view_finance_operations_dashboard` | ⌂ Hôm nay | moved → `financeRoutes.today.dashboard()` (Cockpit replaces in M3) |
| Generate HP (Tuition) `/finance/major/charges` | `create_finance_charges` | ＄ Sinh phí | moved → `financeRoutes.feeGeneration.majorCharges()` |
| Batch Charges `/finance/operations/generate-charges` | `view_finance_operations_generate_charges` | ＄ Sinh phí | moved → `financeRoutes.feeGeneration.batchCharges()` |
| EGC · Generate Charges `/finance/egc/charges` | `generate_egc_finance_charges` | ＄ Sinh phí | moved → `financeRoutes.feeGeneration.egcCharges()` |
| EGC · Block Results `/finance/egc/block-results` | `view_egc_block_results` | ＄ Sinh phí | moved → `financeRoutes.feeGeneration.egcBlockResults()` |
| EGC · Retake Adjustments `/finance/egc/retake-adjustments` | `view_egc_retake_adjustments` | ＄ Sinh phí | moved → `financeRoutes.feeGeneration.egcRetakeAdjustments()` |
| EGC · Carry Forward `/finance/egc/carry-forward` | `view_egc_retake_adjustments` | ＄ Sinh phí | moved → `financeRoutes.feeGeneration.egcCarryForward()` |
| DNG Worklist `/finance/operations/dng-worklist` | `create_finance_payments` | 💳 Thu & Đối soát | moved → `financeRoutes.collect.dngWorklist()` |
| DNG Payment Requests `/finance/dng/payment-requests` | `view_finance_dng_payment_requests` | 💳 Thu & Đối soát | moved → `financeRoutes.collect.dngPaymentRequests()` |
| DNG Webhook Events `/finance/dng/webhook-events` | `view_finance_dng_webhook_events` | 💳 Thu & Đối soát | moved → `financeRoutes.collect.dngWebhookEvents()` |
| Settlement Worklist `/finance/operations/settlement` | `allocate_finance_payment` | 💳 Thu & Đối soát | moved → `financeRoutes.collect.settlement()` |
| Payments `/finance/payments` | `view_finance_payments` | 💳 Thu & Đối soát | moved → `financeRoutes.collect.payments()` |
| DNG Due Reminders `/finance/operations/due-calendar` | `view_finance_operations_due_calendar` | 💳 Thu & Đối soát | moved → `financeRoutes.collect.dueReminders()` |
| DNG Due Reminders (duplicate under EGC Finance) | `view_finance_operations_due_calendar` | — | **removed (dedupe)** — unified into the Thu & Đối soát entry |
| Exceptions Queue `/finance/operations/exceptions` | `view_finance_operations_exceptions` | 🛟 Ngoại lệ | moved → `financeRoutes.exceptions.queue()` |
| Lifecycle Exceptions `/finance/operations/lifecycle-exceptions` | `view_finance_operations_due_calendar` | 🛟 Ngoại lệ | moved → `financeRoutes.exceptions.lifecycle()` |
| Lifecycle History `/finance/operations/lifecycle-exception-history` | `view_finance_operations_due_calendar` | 🛟 Ngoại lệ | moved → `financeRoutes.exceptions.lifecycleHistory()` |
| Charge Ledger (Global) `/finance/charges` | `view_finance_charges` | 🔎 Tra cứu & Audit | moved → `financeRoutes.lookup.chargeLedger()` |
| Invoices `/finance/invoices` | `view_finance_invoices` | 🔎 Tra cứu & Audit | moved → `financeRoutes.lookup.invoices()` |
| Discounts & Funding (Tuition Plans / Scholarships / Student Scholarships / Vouchers) | various | Discounts & Funding (separate group) | **kept unchanged** — routes live outside the Finance module; literal hrefs retained per design §3.2 |
| Student 360 | `view_finance_student_overview` | — | **not in sidebar** — reached via ⌘K + row clicks (design §3.2) |

### Automated acceptance evidence

Backend (Pest, run via `./scripts/dev.sh test`) — **15 passed, 92 assertions**:

- `tests/Feature/Finance/Student360/StudentOverviewShellTest.php` — 5 passed
  (renders identity + 4 balances; echoes valid `focus`; denies without permission;
  hides cross-campus as 404; allows cross-campus with `view_finance_all_campus`).
- `tests/Feature/Finance/Search/FinanceGlobalSearchTest.php` — 4 passed
  (student code → 360; invoice number → owning-student 360 with focus link;
  cross-campus identifier → empty; denies without permission).
- `tests/Feature/Finance/Shell/SemesterContextTest.php` — 3 passed
  (shares `semester` prop to finance users; omits it for non-finance users;
  persists selected semester into session).
- `tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php` — 3 passed
  (regression check — S-009 audit workspace unaffected).

Permissions: `view_finance_student_overview` + `view_finance_all_campus` declared in
`config/permission.php`, synced via `UpdatePermissionsSeeder` (created 2 new perms, no
orphan deletions), mapped to `truong_phong`/`can_bo` (+ super_admin via all-permission sync).

Frontend: `./scripts/dev.sh npm run build` — **success** (5391 modules transformed,
manifest written; only the pre-existing whole-bundle chunk-size advisory). Per-file
`eslint` clean on all new/changed `.ts`/`.vue`. Whole-project `vue-tsc` not run in the dev
container (known OOM/SIGKILL); compilation validated via the production build + per-file eslint.

Routes registered (`artisan route:list`): `finance.students.overview`
(`GET finance/students/{student}`), `finance.search` (`GET finance/search`),
`finance.semester-context.update` (`POST finance/semester-context`).

### Interactive browser smoke

No Playwright/headless harness is configured in this repo, so the interactive
click-through is a manual QA step. Each step's server-side contract is covered by the
named automated tests above; the remaining manual checks are the rendered-UI behaviors:

1. Topbar shows ⌘K button + semester switcher for finance users — *manual* (gating logic
   `canAny([...])` + shared `semester` prop covered by SemesterContextTest).
2. ⌘K → type student code → result → Enter lands on `/finance/students/{id}` — *manual UI*;
   resolver→deep-link + 360 render covered by Search + Student360 tests.
3. Invoice number → owning student's 360 with `?focus=invoice:<id>` — *manual UI*;
   mapping covered by FinanceGlobalSearchTest.
4. Change semester switcher → reload persists selection — *manual UI*; session persistence
   covered by SemesterContextTest.
5. Sidebar shows 5 Finance work-groups + Discounts & Funding, no dead links, permission-gated —
   *manual UI*; all hrefs resolve to registered route names (build + route:list confirm).
