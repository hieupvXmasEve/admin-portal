# FIN-REV-018 - Finance Reporting Collection Progress

## Status

planned

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

Pending implementation.
