# FIN-REV-017 - Finance Reporting Fee Monitor

## Status

planned

## Lane

normal

## Product Contract

Implement the Fee Monitor view inside Finance Reporting. The view answers
whether expected fees have been generated correctly for the selected Finance
semester and current account campus.

The primary row grain is `student x expected_fee_type x semester`. Missing rows
are allowed only for confirmed expected-fee sources. Course retake and exam
resit missing-fee completeness must wait for `ACAD-RET-001` before Reporting
marks missing `retake_fee` or `exam_resit_fee` rows. Until then, this view may
show existing retake/resit Finance charges but must not infer missing expected
fees from raw failed grades.

## Relevant Product Docs

- `docs/stories/E-finance-module-review-2026-06/S-012-fee-tracking-reporting-requirements/overview.md`
- `docs/stories/E-finance-module-review-2026-06/S-016-finance-reporting-shell/overview.md`
- `docs/stories/E-academic-retake-resit-operations-2026-06/S-001-retake-resit-operations/overview.md`
- `docs/stories/E-academic-retake-resit-operations-2026-06/S-001-retake-resit-operations/design.md`

## Portal Impact

none

## Acceptance Criteria

- Add a read-only Fee Monitor query for selected semester and current campus.
- Cover first-pass expected fee sources: tuition plan fees, EGC tuition fees,
  admission/enrollment fees, and BHYT health insurance fees.
- Gate missing course-retake and exam-resit expected-fee logic behind the
  `ACAD-RET-001` source contract.
- Include existing retake/resit charges in generated/collection evidence when
  they already exist.
- Provide summary counts for missing, generated, skipped, voided, blocked, paid,
  partially paid, and outstanding rows where source data supports the state.
- Support the always-visible filters from `FIN-REV-012`: program, intake,
  class/cohort, expected fee type, generation state, student status, and student
  search.
- Provide drilldown links to Student 360 and Lookup & Audit.
- Hand off missing/blocked fee work to Batch Studio with selected context; do not
  mutate Finance records directly.

## Design Notes

- Commands: none.
- Queries: new Finance reporting query class for fee completeness.
- API: read-only Inertia props for the `fee-monitor` view.
- Tables: students, programs/classes/cohorts, semesters, finance charges,
  invoices, payments/applications, EGC source tables, tuition/admission/BHYT
  source tables as verified during implementation.
- Domain rules: do not guess Academic retake/resit intent; do not use stale
  invoice cache as source of truth unless explicitly labeled as cache.
- UI surfaces: extend the Finance Reporting page with the Fee Monitor view.

## Validation

| Layer       | Expected proof                                                                                 |
| ----------- | ---------------------------------------------------------------------------------------------- |
| Unit        | Query tests for expected-source state mapping and retake/resit gating.                         |
| Integration | Inertia page test for Fee Monitor props, filters, campus scope, and drilldown links.           |
| E2E         | Browser smoke if tooling is available; otherwise record why not run.                           |
| Platform    | Targeted ESLint, Prettier, Pint, and `git diff --check` for touched files.                     |
| Release     | Harness evidence records the ACAD-RET-001 gate and any excluded expected-fee sources.          |

## Harness Delta

Register this story as the Fee Monitor implementation slice.

## Evidence

Pending implementation.
