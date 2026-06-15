# Validation

## Proof Strategy

Milestone 2 is implemented and recorded here as one consolidated story. The old
task-sized packets `S-010-08` through `S-010-14` remain historical notes only.

This story includes write-adjacent and write paths, so validation must prove
permission scope, campus scope, state changes, and finance invariants.

## Test Plan

| Layer       | Cases                                                                                                                 |
| ----------- | --------------------------------------------------------------------------------------------------------------------- |
| Unit        | Query/read-model behavior for status cards, grouped ledger, action flags, and allocation preview.                     |
| Integration | Student 360 page props, payment recording, DNG cancel impact, reviewed cancel, campus scoping, and permission denial. |
| E2E         | Rendered status cards, dual ledger lens, action menu, drawers, and focus highlight.                                   |
| Platform    | Production build and targeted frontend lint/prettier for changed Student 360 UI.                                      |
| Logs/Audit  | `finance:audit-invariants` before/after write-path exercise; no new invariant categories.                             |

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php
./scripts/dev.sh test tests/Feature/Finance/Student360/RecordManualPaymentTest.php
./scripts/dev.sh test tests/Feature/Finance/Student360/Student360OverviewTest.php
./scripts/dev.sh test tests/Feature/Finance/Student360/StudentOverviewShellTest.php
./scripts/dev.sh test tests/Feature/Finance/Dng/ReviewedCancelDngTest.php
./scripts/dev.sh test tests/Feature/Finance/Search/FinanceGlobalSearchTest.php
./scripts/dev.sh test tests/Feature/Finance/Shell/SemesterContextTest.php
./scripts/dev.sh test tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php
./scripts/dev.sh artisan finance:audit-invariants
./scripts/dev.sh npm run build
```

## Acceptance Evidence

## Milestone 2 - Student 360 full surface (implemented 2026-06-15)

Scope: four status cards, grouped ledger plus timeline lenses, DNG state
stepper, permission-aware action menu, record-payment / allocation-preview /
reviewed DNG-cancel drawers, installment retry push, and
`focus=<type>:<id>` scroll+highlight. Write paths are thin adapters over
existing tested Actions/Services - no new money math.

**Portal impact: none** (admin/staff Inertia UI only).

**Money state changed in M2** (record payment, DNG cancel/void, allocation
apply, installment push). Feature tests exercise writes on isolated `db_test`
via `RefreshDatabase`; the `finance:audit-invariants` command audits the dev
`asia` dataset.

### M2 routes and permission gates

| Route name                                     | Method | Permission                                                               |
| ---------------------------------------------- | ------ | ------------------------------------------------------------------------ |
| `finance.students.overview`                    | GET    | `view_finance_student_overview`                                          |
| `finance.students.payments.store`              | POST   | `create_finance_payments`                                                |
| `finance.payments.allocate-preview`            | GET    | `allocate_finance_payment`                                               |
| `finance.payments.allocate`                    | POST   | `allocate_finance_payment` (existing)                                    |
| `finance.dng.payment-requests.cancel-impact`   | GET    | `create_finance_payments`                                                |
| `finance.dng.payment-requests.cancel-reviewed` | POST   | `create_finance_payments` (+ `void_finance_charges` when linked charges) |
| `finance.charges.installments.retry-push`      | POST   | `split_installment_finance_charges` policy (existing)                    |

Write adapters wrap: `PaymentService::recordPayment`,
`PreviewManualAllocationQuery` (read), `AllocatePaymentAction`,
`CancelDngPaymentRequestAction`, `PushNextInstallmentAction`.

### Automated acceptance evidence

Backend (Pest, `./scripts/dev.sh test`) - **32 passed, 191 assertions**
(2026-06-15):

**M2 suite** - 22 passed, 136 assertions:

- `tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php` - 3
  passed (preview candidates; denies without `allocate_finance_payment`;
  cross-campus 404).
- `tests/Feature/Finance/Student360/RecordManualPaymentTest.php` - 4 passed
  (records manual payment; rejects non-positive amount; denies without
  permission; cross-campus 404).
- `tests/Feature/Finance/Student360/Student360OverviewTest.php` - 5 passed
  (four status cards + action flags; permission matrix props;
  `unapplied_payment_id`; deferred `ledger_groups` not in initial payload).
- `tests/Feature/Finance/Student360/StudentOverviewShellTest.php` - 5 passed
  (identity + balances; focus echo; permission deny; campus scope).
- `tests/Feature/Finance/Dng/ReviewedCancelDngTest.php` - 5 passed (cancel
  impact JSON; reason+ack validation; cancel pending DNG; void permission gate;
  non-cancellable status block).

**M1 regression re-run** - 10 passed, 55 assertions (no regressions):

- `tests/Feature/Finance/Search/FinanceGlobalSearchTest.php` - 4 passed
- `tests/Feature/Finance/Shell/SemesterContextTest.php` - 3 passed
- `tests/Feature/Finance/Audit/FinanceAuditWorkspacePageTest.php` - 3 passed

Frontend:

- `./scripts/dev.sh npm run build` - **success** (5405 modules; pre-existing
  chunk-size advisory).
- Targeted `eslint` on `resources/js/components/finance/student360/` +
  `Show.vue` + route helpers - **0 errors**.
- Whole-project `vue-tsc` not run (known dev-container OOM).

Performance: `Student360OverviewTest` confirms `ledger` and `ledger_groups` are
deferred - initial Inertia payload omits `ledger_groups`; partial reload loads
it.

### Finance invariant evidence

Command: `./scripts/dev.sh artisan finance:audit-invariants`

| When               | Dataset      | Result                                                                                             |
| ------------------ | ------------ | -------------------------------------------------------------------------------------------------- |
| Before M2 test run | `asia` (dev) | INV-6 failed 28, INV-13 failed 1 - pre-existing dev-data violations; all other invariants passed 0 |
| After M2 test run  | `asia` (dev) | Same counts - M2 adapters did not introduce new invariant categories on dev data                   |

Write-path feature tests (`RecordManualPaymentTest`, `ReviewedCancelDngTest`)
run on `db_test` with `RefreshDatabase` and do not mutate the `asia` audit
dataset.

### Permission matrix

Server-side contracts are covered by the named tests above. Interactive
rendered-UI checks remain manual (no Playwright harness):

| #   | Permission / state                              | Expected UI                                   | Automated contract                                                          | Manual UI               |
| --- | ----------------------------------------------- | --------------------------------------------- | --------------------------------------------------------------------------- | ----------------------- |
| 1   | `view_finance_student_overview` only            | No action items; no write buttons             | `Student360OverviewTest` flags false; `StudentOverviewShellTest` deny paths | Action menu absent      |
| 2   | + `create_finance_payments`                     | Record payment; DNG cancel for pending/pushed | `RecordManualPaymentTest`; `ReviewedCancelDngTest`                          | Drawer submit + flash   |
| 3   | + `allocate_finance_payment`                    | Allocate when `has_unapplied`                 | `ManualAllocationPreviewTest`; `unapplied_payment_id` test                  | Preview drawer + apply  |
| 4   | DNG + linked charges, no `void_finance_charges` | Cancel submit blocked                         | `ReviewedCancelDngTest` -> 403                                              | Void-permission message |
| 5   | DNG with bridged payment                        | Blocking reason; submit disabled              | `ReviewedCancelDngTest` impact `blocking_reasons`                           | Drawer shows block list |
| 6   | `?focus=dng:<id>`                               | DNG card highlighted + scrolled               | `StudentOverviewShellTest` focus echo                                       | Highlight ring on card  |

### Interactive browser smoke (M2 additions)

Manual QA for rendered drawer/focus behaviors not yet click-tested in CI:

1. Status cards + DNG stepper + dual ledger tabs render on a student with
   finance history - manual.
2. Record-payment drawer: DatePicker inside Sheet, submit, flash, drawer closes
    - manual.
3. Allocation preview drawer: candidates load, per-line apply posts allocation
    - manual.
4. DNG cancel drawer: impact, blocking reasons, reason, acknowledgement -
   manual.
5. `focus=dng:<id>` scrolls to and highlights the DNG card - manual.
6. Installment retry posts retry-push - manual (route wired; policy-gated).
