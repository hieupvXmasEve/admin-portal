# Scalability Assessment: Yearly Aggregate Table

Date: 2026-02-25
Scope: `students`, `student_action_logs`, `academic_progression_events`, `semesters`

## TL;DR
Use **incremental materialization** (daily or hourly upsert) for the yearly aggregate table. Keep a narrow on-demand query only for validation/backfill, not primary dashboard path.

## Data-Volume Risks
- `student_action_logs` and `academic_progression_events` are append-heavy audit/event tables. Yearly scans + joins will trend to full/large range scans as history grows.
- Current query patterns include campus filtering via `whereHas(student...)`; at scale this causes join fan-out + poor selectivity if not anchored by composite indexes.
- `student_action_logs` has 5 nullable semester FK columns. Queries that do OR across them (`from|return|intended|dropout|effective`) degrade with volume.
- Eager defaults (`$with = ['changedBy']`, `$with = ['createdBy']`) can inflate memory/IO when aggregates are computed through Eloquent instead of SQL.

## Aggregation Correctness Risks
- Time-key ambiguity:
  - `student_action_logs` has `created_at`, `signed_at`, `effective_at`.
  - `academic_progression_events` has `effective_at` + `semester_id`.
  - `semesters` has `start_date/end_date` and soft deletes.
  Decide one canonical year key per metric. Mixing these will misstate yearly counts.
- Action semester ambiguity:
  - For `student_action_logs`, relevant semester depends on `action_type` (not one fixed column).
  - Naive OR-join to all semester columns risks overcount or wrong year attribution.
- Campus attribution drift:
  - If yearly aggregate uses current `students.campus_id`, historical actions/events after transfer can be attributed to wrong campus.
  - For transfer events, `from_campus_id`/`to_campus_id` may be needed for historical truth.
- Soft delete behavior:
  - `students` and `semesters` are soft-deletable. Aggregation rules must state include/exclude deleted dimensions.

## Index Adequacy (for yearly aggregates)
Current useful indexes:
- `student_action_logs`: `action_type`, `created_at`, `(student_id, action_type)`, `(created_at, action_type)`.
- `academic_progression_events`: `(student_id, effective_at)`, `(semester_id, event_type)`, `event_type`, `trigger_source`.
- `students`: `(campus_id, status)`, `(program_id, status)`, `status`, others.
- `semesters`: `code`, `(start_date, end_date)`, active/archive flags.

Gaps for aggregate workload:
- Missing index for frequent campus-filtered event windows:
  - `academic_progression_events`: add `(effective_at, semester_id, event_type, student_id)` or `(semester_id, effective_at, event_type, student_id)` based on primary predicate order.
- Missing index for actor/date analytics in action logs:
  - `student_action_logs`: add `(created_at, action_type, changed_by_user_id, student_id)`.
- `student_action_logs` OR across 5 semester columns is structurally index-hostile.
  - If keeping schema, avoid OR in hot path; normalize into a derived fact view/table before yearly rollup.

## Recommendation: Incremental Materialization vs On-Demand
Recommendation: **Incremental materialization**.

Why:
- Predictable query latency for dashboards/report exports.
- Lower DB CPU than repeated yearly scans + multi-join OR predicates.
- Better correctness controls (frozen aggregation rules, idempotent recompute window).

Suggested pattern:
- Materialized table grain: `(year, campus_id, action_type, event_type, metric_key)` + counts.
- Watermark strategy:
  - Primary watermark: source `updated_at`/`created_at`.
  - Recompute sliding window: last 90 days to absorb late updates/fixes.
- Job cadence: hourly for near-real-time; daily if SLA allows.
- Backfill command: full rebuild by year range.
- Validation: weekly compare sample years against on-demand reference SQL.

When on-demand is still OK:
- Low-frequency ad hoc diagnostics.
- One-off reconciliation queries.

## Minimal Next SQL Changes
- Add targeted composite indexes for aggregate predicates.
- Create a canonicalized staging query/view for `student_action_logs` with one `derived_semester_id` and one `derived_event_at` per row (by `action_type` rules).
- Build yearly aggregate table with idempotent upsert job.

## Unresolved Questions
- Canonical year key per metric: `effective_at` vs `created_at` vs semester year?
- Historical campus truth source: event/action-time campus vs current student campus?
- Should soft-deleted students/semesters remain in published yearly aggregates?
- Required freshness SLA: hourly, daily, or batch-at-night?
