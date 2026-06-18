# FIN-REV-018 - Finance Reporting Collection Progress

## Status

implemented

## Lane

normal

## Product Contract

Implement the Collection Progress view inside Finance Reporting. The view
answers how much has been billed, paid, and left outstanding for the selected
Finance semester and current account campus.

The primary row grain is `student x semester_balance`. Money terms must map to
canonical settlement/ledger truth before display: billed, paid, outstanding,
overdue, overpaid, and unapplied. Cached invoice snapshot columns may be used
only when the implementation proves they are refreshed from the canonical source
or labels the statistic as a cache/snapshot value.

## Relevant Product Docs

- `docs/stories/E-finance-module-review-2026-06/S-012-fee-tracking-reporting-requirements/overview.md`
- `docs/stories/E-finance-module-review-2026-06/S-016-finance-reporting-shell/overview.md`
- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/system-architecture.md`

## Portal Impact

none

## Acceptance Criteria

- Add a read-only Collection Progress query for selected semester and current
  campus.
- Compute or derive billed, paid, outstanding, overdue, overpaid, and unapplied
  balances from canonical settlement/ledger truth.
- Include summary cards and grouped breakdowns by fee type, program, intake,
  class/cohort, balance state, aging/due bucket, and lifecycle exception flag.
- Support the always-visible filters from `FIN-REV-012`: program, intake,
  class/cohort, fee type, balance state, aging/due bucket, student status, and
  student search.
- Show visible computed-at freshness timestamps for statistics.
- Provide drilldown links to Student 360 and Lookup & Audit.
- Do not expose export or mutation actions.

## Design Notes

- Commands: none.
- Queries: new Finance reporting query class for collection progress.
- API: read-only Inertia props for the `collection-progress` view.
- Tables: invoice lines, student invoices, payment applications, payments,
  discount allocations, Finance charges, DNG requests, students, semesters.
- Domain rules: settlement/ledger read model is source of truth; no double count
  between invoice and DNG rails.
- UI surfaces: extend the Finance Reporting page with the Collection Progress
  view.

## Validation

| Layer       | Expected proof                                                                                   |
| ----------- | ------------------------------------------------------------------------------------------------ |
| Unit        | Query tests for billed/paid/outstanding/unapplied formulas and aging buckets.                    |
| Integration | Inertia page test for Collection Progress props, filters, campus/semester scope, and drilldowns. |
| E2E         | Browser smoke if tooling is available; otherwise record why not run.                             |
| Platform    | Targeted ESLint, Prettier, Pint, and `git diff --check` for touched files.                       |
| Release     | Harness evidence records formula lineage for every displayed money statistic.                    |

## Harness Delta

Register this story as the Collection Progress implementation slice.

## Evidence

Implemented on 2026-06-18.

### Money lineage (canonical settlement/ledger truth)

- `billed` / `paid` / `outstanding` — summed per student from
  `SettlementService::deriveInvoiceSnapshot()` (`net` / `paid` / `remaining`) over
  the student's non-cancelled `student_invoices` in the selected semester.
- `overdue` — invoice `remaining` where `due_date` is past; aging bucket from the
  worst overdue invoice age (`not_due`, `d_1_30`, `d_31_60`, `d_61_90`,
  `d_90_plus`).
- `overpaid` — applied cash beyond net due:
  `max(0, Σ active-line payment_applications − invoice net)` from the same loaded
  ledger rows (snapshot clamps paid to net, so this is a derived anomaly signal).
- `unapplied` — student-level available cash: completed `payments.amount` minus
  `Σ payment_applications.amount` per payment, summed by student. Invoice rail
  only; the DNG rail is not counted, so there is no double count.

### Surfaces

- Read-only `collection-progress` Inertia props on `/finance/reporting` (grain
  `student × semester_balance`, scoped to the account campus + selected Finance
  semester).
- Summary cards (billed/paid/outstanding/overdue/overpaid/unapplied + collection
  rate) and grouped breakdowns by fee type, program, intake, cohort, balance
  state, aging/due bucket, and lifecycle-exception flag.
- Always-visible filters: program, intake, cohort, fee type, balance state,
  aging/due bucket, student status, student search.
- Visible `computed_at` freshness; drilldowns to Student 360 and Lookup & Audit
  (invoice). No export or mutation actions.

### Validation

| Layer       | Proof                                                                                                                                                                                    |
| ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit        | `tests/Unit/Finance/Reporting/CollectionProgressCatalogTest.php` — 4 passed (aging buckets, vocabulary).                                                                                 |
| Integration | `tests/Feature/Finance/Reporting/CollectionProgressViewTest.php` — 6 passed (props, formulas, filters, scope, drilldowns); shell test updated. 24 reporting tests / 180 assertions pass. |
| E2E         | Not run — no browser tooling invoked in this slice.                                                                                                                                      |
| Platform    | Targeted ESLint, Prettier, Pint, and `git diff --check` clean on touched files.                                                                                                          |
| Release     | Money-statistic lineage recorded above (formula → canonical source for every displayed figure).                                                                                          |
