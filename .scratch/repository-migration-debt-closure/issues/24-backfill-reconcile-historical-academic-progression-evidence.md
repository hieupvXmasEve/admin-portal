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
