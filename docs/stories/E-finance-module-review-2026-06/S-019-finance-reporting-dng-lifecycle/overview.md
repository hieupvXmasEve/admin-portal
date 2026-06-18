# FIN-REV-019 - Finance Reporting DNG/Payment Lifecycle

## Status

planned

## Lane

normal

## Product Contract

Implement the DNG/Payment Lifecycle view inside Finance Reporting. The view
answers where payment requests, webhooks, payment bridge, invoices, and
allocations are stuck.

The primary row grain is `DNG/payment request`. This view must not be
hard-filtered by the global Finance semester. It remains campus-bound and must
display request creation time plus related semester lineage for each row.

## Relevant Product Docs

- `docs/stories/E-finance-module-review-2026-06/S-012-fee-tracking-reporting-requirements/overview.md`
- `docs/stories/E-finance-module-review-2026-06/S-016-finance-reporting-shell/overview.md`
- `docs/stories/E-finance-module-review-2026-06/S-005-dng-security-and-idempotency/`
- `docs/stories/E-finance-module-review-2026-06/S-009-finance-audit-workspace/`

## Portal Impact

none

## Acceptance Criteria

- Add a read-only DNG/Payment Lifecycle query for current campus.
- Do not hard-filter rows by the global Finance semester; expose related semester
  as an explicit optional filter.
- Resolve semester lineage from `dng_payment_requests.semester_id`, then linked
  charge semesters through `dng_payment_request_charges.finance_charge_id ->
  finance_charges.semester_id`, with multi-semester and unknown-semester states.
- Show request creation time, due date, related semester, status, payment bridge
  status, webhook state, invoice state, allocation/reconciliation state, and
  attention reason.
- Implement the accepted attention buckets: failed requests, failed/invalid or
  mismatched webhooks, `paid_uninvoiced`, `pending` older than 60 minutes, and
  `pushed_to_dng` past due date.
- Support filters for created date range, DNG status, payment bridge status,
  webhook state, related semester, student/search identifiers, paid date range,
  invoice status, allocation/reconciliation state, cancel/retry/error state, fee
  type, amount range, and outside-selected-semester flag.
- Provide drilldown links to DNG request detail, webhook detail, Student 360, and
  Lookup & Audit.
- Do not expose cancel, retry, allocation, reconciliation, or export actions.

## Design Notes

- Commands: none.
- Queries: new Finance reporting query class for DNG/payment lifecycle.
- API: read-only Inertia props for the `dng-lifecycle` view.
- Tables: `dng_payment_requests`, `dng_payment_request_charges`,
  `dng_webhook_events`, `finance_charges`, invoices, payments, payment
  applications, students, semesters.
- Domain rules: DNG lifecycle rows remain visible across semesters; related
  semester is evidence, not an implicit filter.
- UI surfaces: extend the Finance Reporting page with the DNG/Payment Lifecycle
  view.

## Validation

| Layer       | Expected proof                                                                                       |
| ----------- | ---------------------------------------------------------------------------------------------------- |
| Unit        | Query tests for attention buckets, semester lineage, multi/unknown semester states, and filters.     |
| Integration | Inertia page test proves global semester does not hide DNG lifecycle rows from other semesters.      |
| E2E         | Browser smoke if tooling is available; otherwise record why not run.                                 |
| Platform    | Targeted ESLint, Prettier, Pint, and `git diff --check` for touched files.                           |
| Release     | Harness evidence records DNG/webhook/payment lineage and confirms no mutation actions were exposed. |

## Harness Delta

Register this story as the DNG/Payment Lifecycle implementation slice.

## Evidence

Pending implementation.
