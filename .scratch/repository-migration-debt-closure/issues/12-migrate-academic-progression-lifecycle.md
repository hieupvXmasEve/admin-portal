# Migrate Academic Progression & Lifecycle

Status: ready-for-agent

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Establish Academic Progression & Lifecycle ownership for Program Enrollment, Study Stage, Transcript Entry, GPA, Student Action, Decision, EGC progression, best-attempt, graduation, and Student Hub workflows without collapsing their distinct state machines.

This is the architecture and compatibility slice. It must introduce a Progression-owned Student Hub Query and a versioned read projection/contract for Delivery evidence while preserving current supported behavior. It is read-only with respect to historical academic evidence; historical data backfill, reconciliation, and compatibility retirement are owned by [24 — Backfill and reconcile historical Academic Progression evidence](24-backfill-reconcile-historical-academic-progression-evidence.md).

## Acceptance criteria

- [ ] Program Enrollment Status, Study Stage, Account Status, and Student identity remain separate concepts and persistence responsibilities.
- [ ] Transcript, GPA, standing, best-attempt, EGC, lifecycle actions, Decisions, and graduation derive from Progression-owned evidence; the transition preserves legacy-only outcomes through the declared compatibility projection.
- [ ] Student Hub behavior, permissions, filters, lifecycle guards, audit timeline, and notifications remain compatible.
- [ ] Finance and other consumers receive lifecycle facts through approved contracts/events rather than Academic persistence reads.
- [ ] No historical lifecycle data mutation or compatibility-path removal occurs in this slice; each requires the separately approved data checkpoint in issue 24.

## Blocked by

- [Migrate Student Registry identity and Guardian relationships](06-migrate-student-registry-identity-guardians.md)
- [Migrate Academic Catalog & Calendar management](08-migrate-academic-catalog-calendar.md)
- [Migrate assessment, gradebook and Course Result](11-migrate-assessment-gradebook-course-result.md)

## Follow-up data cutover

- [Backfill and reconcile historical Academic Progression evidence](24-backfill-reconcile-historical-academic-progression-evidence.md) owns the approved write checkpoint, reconciliation, cutover, and removal of the compatibility projection after this issue establishes it.

## Comments

### 2026-07-24 — split into architecture and approved data-cutover slices

The prerequisite issues 06, 08, and 11 are complete. At maintainer direction, the previous blocker is split into a dedicated child issue rather than combining read-boundary work with historical data writes.

Student Hub still obtains registrations, scores, graduation, and export payloads from frozen `app/Services/StudentAcademicSummaryService`. This issue must replace that ownership with a real Progression Query; a forwarding façade is not acceptable. Its compatibility projection must preserve legacy-only `academic_records` outcomes and the registrations payload fields `meets_attendance_requirement`, `grade_status`, and `completion_status` until issue 24 completes an approved reconciliation.

No application code was retained from the rejected façade attempt. This issue may now proceed with the read-only architecture and compatibility work; issue 24 remains `needs-info` pending human approval before any data mutation.
