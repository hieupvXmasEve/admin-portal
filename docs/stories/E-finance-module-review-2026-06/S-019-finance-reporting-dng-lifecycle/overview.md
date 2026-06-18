# FIN-REV-019 - Finance Reporting DNG/Payment Lifecycle

## Status

implemented

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

Implemented on 2026-06-18.

### Lineage (DNG / webhook / payment / invoice / allocation)

- Row grain is one `dng_payment_requests` row, scoped to the account campus
  through the linked `students.campus_id`. The lens is NOT hard-filtered by the
  global Finance semester — a stuck request stays visible under any selected
  semester.
- **Related semester** — distinct union of `dng_payment_requests.semester_id`,
  the direct `finance_charge_id -> finance_charges.semester_id`, and every pivot
  `dng_payment_request_charges.finance_charge_id -> finance_charges.semester_id`.
  Resolved to `single` / `multi` / `unknown` (empty set). `outside_selected_semester`
  is true only when the lineage is non-empty and excludes the selected semester.
- **DNG status** — `dng_payment_requests.status` (pending → reconciled,
  failed, cancelled, cancel_pushed_to_dng).
- **Payment bridge** — `bridged` when `payment_id` is set, else `not_bridged`.
- **Webhook state** — worst-first collapse of `dng_webhook_events`
  (`is_valid_checksum` + `processing_status`): invalid_checksum > mismatch >
  failed > pending > ok > none.
- **Invoice state** — `invoiced` (serial present or status paid_invoiced/reconciled),
  `uninvoiced` (paid_uninvoiced), else `not_applicable`.
- **Allocation/reconciliation** — `reconciled`, `pending_reconciliation`
  (paid_uninvoiced/paid_invoiced), else `not_applicable`.
- **Attention buckets** (accepted set) — `failed_request`, `webhook_problem`
  (failed/invalid/mismatched webhook), `paid_uninvoiced`, `pending_stale`
  (`pending` older than 60 min), `overdue_pushed` (`pushed_to_dng` past due date).

### Surfaces

- Read-only `dng-lifecycle` Inertia props on `/finance/reporting` (grain
  `DNG/payment request`, campus-bound, semester-agnostic).
- Attention-first queue (stuck rows sort ahead of clean rows, ties by newest
  creation time) with summary tiles (total, needs-attention, failed,
  webhook problems, paid-uninvoiced, overdue/stale) and grouped breakdowns by
  attention bucket, DNG status, webhook state, payment bridge, invoice state,
  related semester, and fee type.
- Filters: created/paid date ranges, DNG status, payment bridge, webhook state,
  related semester, student/identifier search, invoice state, allocation state,
  cancel/retry/error state, fee type, amount range, and outside-selected-semester.
- Visible `computed_at` freshness; drilldowns to Student 360, DNG request detail,
  the focused DNG webhook event (problem event preferred, else latest — falls back
  to the webhook index), and Lookup & Audit. No cancel, retry, allocation,
  reconciliation, or export actions are exposed.

### Performance (proportionate, catalog stays source of truth)

- Bounded scan: at most `MAX_SCAN = 2000` most-recent campus requests are pulled
  into memory for classification. When the campus+filter scope exceeds it, the
  cut is surfaced (`meta.truncated` / `meta.total_matched` / `meta.scan_cap` + a UI
  banner) and logged — never silently dropped.
- DB pushdown: column-derived filters (payment bridge, invoice state, allocation
  state, cancel/error flow, and status-only attention buckets) are pre-narrowed at
  the DB layer as EXACT mirrors of the catalog classifiers; the authoritative cut
  still happens in the PHP `applyComputedFilters` pass. Time-sensitive
  (pending_stale, overdue_pushed) and event-sensitive (webhook state/problem, flow
  retrying) filters stay PHP-only so a clock/skew can never exclude a valid row.

### Validation

| Layer       | Proof                                                                                                                                                                                                |
| ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit        | `tests/Unit/Finance/Reporting/DngLifecycleCatalogTest.php` — 7 passed (attention buckets, webhook collapse, bridge/invoice/allocation/flow classifiers, semester lineage).                            |
| Integration | `tests/Feature/Finance/Reporting/DngLifecycleViewTest.php` — 10 passed / 97 assertions (props + scan meta, semester-not-hidden contract, lineage single/multi/unknown, attention buckets, webhook problem + focused webhook deep-link, DB-pushdown bridge/invoice filters, status/outside/related filters, sort, campus scope, no mutation actions); shell test updated. Full reporting suite: 41 passed / 314 assertions. |
| E2E         | Not run — no browser tooling invoked in this slice.                                                                                                                                                   |
| Platform    | Targeted ESLint, Prettier, Pint, and `git diff --check` clean on touched files.                                                                                                                      |
| Release     | Read-only lens: DNG/webhook/payment/invoice/allocation lineage recorded above; a dedicated feature test asserts no cancel/retry/allocation/reconciliation/export action is exposed.                  |
