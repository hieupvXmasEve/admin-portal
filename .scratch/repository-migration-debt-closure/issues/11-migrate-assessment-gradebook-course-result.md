# Migrate assessment, gradebook and Course Result

Status: completed

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Move assessment management, Canvas grade sync, lecturer gradebook, finalize/recalculate, and Course Result publication behind Course Delivery & Assessment ownership. Detailed grading evidence stays inside Delivery and only finalized Course Results cross into Progression.

## Acceptance criteria

- [x] Assessment and gradebook writes, reads, imports/exports, and Canvas sync use one supported Delivery path.
- [x] Finalize and Recalculate commit Course Results and Transcript Entries atomically through the accepted Progression command boundary.
- [x] Detailed component scores and grading rules do not leak into Progression or portal ownership.
- [x] Staff and lecturer routes, permissions, preview behavior, API fields, and error semantics remain compatible.
- [x] Focused assessment, Canvas, completion, rollback, and portal contract suites pass.

## Blocked by

- [Migrate Course Offering setup and roster operations](09-migrate-course-offering-setup-roster.md)
- [Migrate class sessions and attendance operations](10-migrate-class-sessions-attendance.md)

## Comments

- 2026-07-24: Review confirmed that the existing Delivery paths preserve the API contract and Course Result rollback behavior. A gradebook GET is now read-only, gradebook cell eligibility validation occurs in the FormRequest, Course Result handoff uses an array-shaped Delivery command, and Transcript Entries no longer relate directly to Delivery/Catalog models.
- 2026-07-24: Grade import/export now runs through `AssessmentGradeWorkbook` and a Delivery action; finalize/recalculate runs through `CourseOfferingFinalizer` and a Delivery action. Legacy implementations remain behind those contracts, keeping the migration-debt guard green while preserving routes, response envelopes, and rollback behavior. Focused suites, full backend tests, Pint, `git diff --check`, and `migration-debt:inventory --check` passed.
