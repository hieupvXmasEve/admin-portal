# Backfill and reconcile historical Academic Progression evidence

Status: needs-info

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## Depends on

[Migrate Academic Progression & Lifecycle](12-migrate-academic-progression-lifecycle.md)

## What to build

After issue 12 establishes the Progression-owned Student Hub Query and compatibility projection, backfill historical academic outcomes from the legacy compatibility source into Progression-owned Transcript Entries. Reconcile records and derived GPA, standing, best-attempt, EGC, graduation, and Student Hub/export results before cutting off the compatibility projection.

This issue is data-affecting. Design and read-only preflight are allowed; no write, conversion, merge, cleanup, or compatibility-path removal may run until the checkpoint below receives explicit human approval.

## Acceptance criteria

- [ ] A read-only preflight identifies the exact source records, target Transcript Entries, unmatched records, duplicate candidates, and conflict rows using stable business identifiers.
- [ ] The proposed backfill is idempotent, restart-safe, scope-constrained, transactionally safe where possible, and has a documented mixed-version and rollback plan.
- [ ] Every mapped outcome preserves final percentage, letter grade, pass/fail, attempted and earned credits, quality points, attempt number, finalization time, GPA exclusion, standing, graduation, and prerequisite effects.
- [ ] Reconciliation proves no unexplained difference in transcript rows, GPA, academic standing, best attempt, EGC/graduation readiness, Student Hub registration fields, exports, decisions, lifecycle timeline, and affected portal responses.
- [ ] A human approves the checkpoint before execution; no record is guessed, silently overwritten, or deleted.
- [ ] The compatibility projection is removed only after reconciliation passes, zero supported consumers remain, and a separate removal approval is recorded.

## Required human-approval checkpoint

Before execution, append all of the following and stop for explicit approval:

1. Exact environment, source/target tables, record scope, and stable identifiers.
2. Read-only counts, exception rows, duplicate/conflict rows, and expected before/after totals.
3. Mapping rules and invariants for outcomes, GPA, standing, best-attempt, EGC, graduation, decisions, and lifecycle history.
4. Idempotency and restart behavior, transaction strategy, backup/recovery, rollback, and mixed-version deployment plan.
5. Student/lecturer portal, queue, notification, export, audit, and Finance-consumer implications.
6. The exact command or migration proposed, its dry-run output, and the named human approver.

## Non-goals

- Changing Student Hub routes, permissions, filters, or payload fields.
- Changing assessment component or attendance evidence ownership.
- Deleting `academic_records` or other historical evidence without separate removal approval.
- Inventing missing academic history or resolving ambiguous mappings automatically.

## Comments

- 2026-07-24: Created at maintainer direction as the required separate data checkpoint for issue 12. No data mutation, migration, backfill, cleanup, or compatibility retirement is authorized or has been attempted.

### 2026-07-24 — read-only preflight checkpoint; explicit approval required

Environment: local Docker application environment (`Asia University`, Laravel 13.6.0, PHP 8.4.23, `Asia/Ho_Chi_Minh`). Scope is explicit `--all`: live, final `academic_records` into `transcript_entries`, using stable identifier `academic_records.id -> transcript_entries.course_result_id` and duplicate business key `student_id + semester_id + unit_id + attempt_number`.

Read-only command and output:

```text
./scripts/dev.sh artisan academic:preflight-transcript-backfill --all --format=table
source_final_outcomes=2052
target_transcript_entries=2052
ready_to_backfill=0
already_matching=2052
conflicts=0
invalid_sources=0
ambiguous_sources=0
date_precision_warnings=2052
unmatched_targets=0
```

`--format=json` emits the stable identifier and every row in each exception category. There are no conflict, invalid, ambiguous, or unmatched rows. All 2,052 matching rows have `legacy_finalization_is_date_only`: the legacy source column is `academic_records.grade_finalized_date` (DATE), while the target stores `transcript_entries.finalized_at` (timestamp). The preflight confirms the dates agree but cannot prove or recreate the missing time-of-day; no time is guessed.

Mapping and invariants: final legacy outcomes map source id, student, course offering, semester, unit, program, campus, attempt number, final percentage, letter grade, attempted/earned credits, quality points, pass/fail, GPA exclusion, standing, graduation, and prerequisite flags field-for-field. Missing required source values, duplicate business attempts, target differences, and target rows with no live final source are blocking exceptions. `grade_finalized_date` may only be promoted after a human approves a documented date-to-timestamp policy. GPA, standing, best-attempt, EGC/graduation, Student Hub/export, Decisions, lifecycle timeline, and portal/Finance consumers are not recomputed or changed by this preflight.

Idempotency and recovery plan for any future approved write: use the unique `course_result_id`, scope by explicit student/semester or `--all`, insert only absent matching candidates, abort on every exception, and process bounded chunks in DB transactions. Do not overwrite or delete target/source rows. Capture a database backup and per-run created-id ledger before execution; rollback may delete only rows recorded by that run. During mixed-version deployment, keep the compatibility projection and write-path dual publishing active; pause queues/notifications only if their reconciliation evidence changes. Finance, student/lecturer API responses, exports, audit, and notification payloads must be compared read-only before any removal approval.

The only implemented command is the read-only preflight above; it deliberately has no `--apply` option. A write command is not proposed for execution because this preflight has zero eligible rows and the finalization-time policy is unresolved. Named human approver: **unassigned**. No approval has been granted, no mutation has run, and the compatibility projection remains in place. Status stays `needs-info`.
