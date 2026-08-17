---
id: ADR-0033
title: "Program Enrollment is separate from Student Identity"
status: accepted
owner: Platform Team
last_verified: 2026-08-17
scope: architecture-decision
---

# Program Enrollment is separate from Student Identity

Program, Curriculum Version, Intake Academic Period, and their lifecycle history belong to an Academic Progression & Lifecycle-owned Program Enrollment rather than Student Identity. Swinx initially enforces at most one primary active Program Enrollment per Student, while the aggregate preserves transfer and historical enrollments without expanding the shared Student reference.

## Canonical lifecycle status (2026-08-17, plan 260817-0017)

`students.status` is write-dead: set once at student creation, never synced
back by any Program Enrollment transition. The canonical current lifecycle
status is the student's primary (`is_primary = 1`) `program_enrollments` row —
`enrollment_status` + `study_stage`, collapsing `withdrawn` to `dropout` and
`active` (no `study_stage`) to `active`. `students.status` remains the
fallback for a student with no materialized primary enrollment, and the
deliberate historical source for three EGC rules that specifically want
stage-at-admission (`ListEgcRetakeAdjustmentsQuery`,
`StudentActionExcelRowMapper`, `CourseCompletionService`).

**Tie-break, binding:** a student may legally hold more than one
`is_primary = 1` row (the unique index constrains only primary+active) —
**highest `id` wins**, both in the SQL projection
(`App\Shared\Support\Academic\StudentLifecycleProjection`) and the
`Student::primaryEnrollment()` relation (`HasOne` + `latestOfMany('id')`).
