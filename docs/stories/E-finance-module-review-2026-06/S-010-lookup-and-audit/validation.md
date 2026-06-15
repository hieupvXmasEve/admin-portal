# Validation

## Proof Strategy

Milestone 5 is read-only — it exercises list queries, Inertia presentation, and
audit graph rendering only. Validation must prove:

- Extracted queries preserve filter + campus-scope behavior and add whitelisted
  server sort where missing.
- Per-surface permission gates unchanged; Batch Studio hand-off respects action
  permissions.
- Three lookup pages converge on `useDataTable` standard (sort, filter, paginate,
  sticky header, row→360, selection bar).
- Audit Workspace renders full money-flow panel from deferred graph props.
- `finance:audit-invariants` counts **unchanged** (sanity check, not a write-path
  gate).

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | `grantFinance()` helper loads without redeclare clash |
| Integration | Charge filter + sort; invoice filter + sort; payment filter + date_range + sort; campus scope; `LookupAuthzTest` permission matrix |
| E2E | Charge/invoice/payment sort + filter + row→360; multi-select → Batch Studio navigation; payments date-range UI; audit money-flow graph + signed timeline colors; optional `finding_code` banner when M3 merged |
| Platform | Targeted eslint on changed lookup + audit Vue/TS files |
| Logs/Audit | `finance:audit-invariants` sanity — zero count drift vs pre-M5 baseline |

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Lookup
./scripts/dev.sh artisan finance:audit-invariants
./scripts/dev.sh npm run lint -- resources/js/pages/Finance/Charges/Index.vue resources/js/pages/Finance/Invoices/Index.vue resources/js/pages/Finance/Payments/Index.vue resources/js/pages/Finance/Audit/Workspace.vue resources/js/components/finance/lookup resources/js/components/finance/audit/MoneyFlowGraph.vue resources/js/composables/useLookupSelection.ts
```

## Acceptance Evidence

### Milestone 5 — Lookup & Audit

Scope: unified lookup standard for Charge Ledger, Invoices, and Payments;
Audit Workspace money-flow graph + timeline polish. Read-only — no new money math.

**Portal impact: none** (admin/staff Inertia UI only).

_Status: implemented — automated evidence recorded 2026-06-16._

#### Automated acceptance

- [x] `./scripts/dev.sh test tests/Feature/Finance/Lookup` — **9 passed** (charges 4, invoices 2, payments 3)
- [x] `./scripts/dev.sh test tests/Feature/Finance/FinanceChargeControllerTest.php` — **1 passed** (student-scoped charges preserved)
- [x] Targeted eslint on changed frontend files — **0 errors** (`npm exec eslint` on 8 changed paths)
- [x] `./scripts/dev.sh artisan finance:audit-invariants` — ran successfully; **no new write paths introduced** (pre-existing INV-6/INV-13 counts unchanged by read-only work)

#### Browser smoke (manual — not run in CI)

- [ ] **Charge Ledger:** filter, sort, row→360 (`focus=charge:`), selection bar →
      Batch Studio (if permitted)
- [ ] **Invoices:** sort, row→360 (`focus=invoice:`), Excel export, selection bar
- [ ] **Payments:** date-range filter, sort, row→360 (`focus=payment:`), stats
      cards
- [ ] **Audit Workspace:** money-flow graph columns + edge list; signed timeline
      +/− colors; derived-balance drift highlight; optional `finding_code` banner

#### Permission matrix (server-side contracts)

| Surface | Index permission | Batch hand-off (if shown) |
| --- | --- | --- |
| Charge Ledger | `view_finance_charges` | `create_finance_payments` (DNG), reminders ops permission |
| Invoices | `view_finance_invoices` | same |
| Payments | `view_finance_payments` | same |
| Audit Workspace | `view_finance_audit_workspace` | n/a (read-only) |