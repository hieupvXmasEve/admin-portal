# 0008 Store EGC Defer Start Block As Audit Metadata

Date: 2026-05-31

## Status

Accepted

## Context

Staff need the student-action report to answer when an EGC student deferred:
the source semester and whether the defer started from Block 1 or Block 2.

`student_action_logs.from_semester_id` already stores the source semester.
However, the audit record does not store the EGC block within that semester.
`egc_blocks.block_number` must not be used as an inferred replacement because
it is finance/progression runtime data and the current implementation permits
block numbers greater than two in a semester.

## Decision

Store a nullable `egc_defer_from_block_number` audit snapshot on
`student_action_logs`.

- Valid values are `1` and `2`.
- The field applies only to EGC `ACADEMIC_DEFER` actions.
- New EGC defer actions recorded through the web form require an explicit block.
- Existing EGC defer actions are backfilled to Block 1 as a deterministic
  default. Staff can correct individual records later.
- Major-program defer actions keep the field null.
- The field is reporting metadata only. It does not change tuition, finance
  charge, or course-registration behavior.

## Alternatives Considered

1. Infer the value from `egc_blocks.block_number`. Rejected because it is not a
   stable half-of-semester audit field and historical logs cannot be mapped
   reliably.
2. Store the value on `defer_cases`. Rejected because historical action logs do
   not all have a linked defer case and the requested surface is the academic
   audit report.
3. Leave historical records null. Rejected because staff requested a Block 1
   default followed by manual correction where needed.

## Consequences

Positive:

- Reports can filter EGC defers by source semester and source block.
- Historical records receive a predictable initial value.
- Finance behavior remains unchanged.

Tradeoffs:

- The backfill value is intentionally approximate until staff corrections are
  applied.
- The web detail edit flow must support correcting the stored block.

## Follow-Up

- Update the student-action audit report, export, detail view, web create flow,
  and import boundary.
- Add targeted proof for persistence, backfill verification, filtering, export,
  and correction.
