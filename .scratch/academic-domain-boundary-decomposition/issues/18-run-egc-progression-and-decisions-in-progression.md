# Run EGC progression and Decisions inside Academic Progression & Lifecycle

Status: ready-for-human

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Move placement, English-level changes, IELTS evidence, EGC course-stage transitions, Student Actions, Decisions, and missing-decision reporting behind Academic Progression & Lifecycle. Course Completion supplies Course Results; Progression owns the resulting Study Stage and Program Enrollment changes and publishes durable facts for other contexts.

## Acceptance criteria

- [x] Placement, IELTS certificate/scan, English-level changes, and course-stage transitions update Progression-owned state rather than Student Identity.
- [x] Student Actions and Decisions retain their existing authorization, attachment, audit, and missing-decision rules.
- [x] EGC progression consumes Course Results through the accepted boundary without reading Delivery gradebook internals.
- [x] Program Enrollment Status and Study Stage remain separate and invalid transitions are rejected with no partial writes.
- [x] Student-facing progression timeline and staff reports retain their current contracts and historical evidence.
- [x] Progression events publish through the shared Domain Event boundary after commit and remain idempotent.
- [x] EGC, Decision, Student Action, report, architecture, and student portal tests pass.

## Blocked by

- [Issue 16: Commit Course Result and Transcript Entry atomically](16-commit-course-result-and-transcript-atomically.md)
- [Issue 17: Run defer, resume, and dropout through Program Enrollment and Finance contracts](17-run-student-lifecycle-through-progression-and-finance.md)

## Verification

- Passed: focused EGC, placement, Program Enrollment, Student Action, Decision, portal-compatibility, and architecture suite (42 tests, 189 assertions); PHP Pint; student portal typecheck with a 4 GB Node heap (existing Nuxt warnings only).
- Implemented: placement, IELTS evidence, English levels, course-stage transitions, Student Actions, Decisions, and missing-decision reporting now live in Progression. EGC consumes Course Results plus an explicit progression context; it does not import Delivery models or services. Program Enrollment owns EGC state, and post-commit facts are durable for every student, including students without portal accounts.
