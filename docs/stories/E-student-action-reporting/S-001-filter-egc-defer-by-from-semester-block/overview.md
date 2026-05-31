# Overview

## Current Behavior

`GET /reports/student-actions` exposes a `Semester` filter backed by
`semester_id`. The query matches any semester-related field on an action log:
source, return, intended intake, dropout, or effective semester. It therefore
cannot answer the narrower question "which students deferred from this
semester?"

`student_action_logs.from_semester_id` already records the defer source
semester. The query layer already accepts `from_semester_id`, but the report UI
and export boundary do not expose it.

For EGC students, staff also need to know whether defer started from Block 1 or
Block 2 of the source semester. No audit field currently stores that snapshot.
Historical EGC defer logs cannot infer it reliably from runtime EGC block data.

## Target Behavior

- The report replaces its generic `Semester` dropdown with `From Semester`.
- The report adds an EGC `From Block` dropdown with Block 1 and Block 2.
- The list and Excel export apply the same source-semester and source-block
  filters.
- The report, action history, action detail, and export show the stored EGC
  defer source block where applicable.
- New EGC defer actions require Block 1 or Block 2 while keeping the existing
  current-semester-only create flow.
- Existing EGC defer logs are backfilled to Block 1. Staff can correct an
  individual action from its detail edit flow.
- Major-program defer records keep the EGC block field empty.

## Affected Users

- Admin and academic staff reviewing student-action reports.
- Staff recording or correcting EGC student defer actions.

## Affected Product Docs

- `docs/db-flow.md`
- `docs/codebase-summary.md`
- `docs/TEST_MATRIX.md`

## Non-Goals

- Change tuition, preserved-fee, finance-charge, or course-registration logic.
- Derive the audit snapshot from `egc_blocks`.
- Open historical semester selection on the web create form.
- Redesign EGC charge generation or block progression.
