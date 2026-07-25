# Cut over the Lecturer API and lecturer portal

Status: completed

Portal impact: lecturer

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Cut lecturer API workflows over from legacy controllers/services to the owning Identity, Course Delivery & Assessment, Facilities, and Notification boundaries. Update the lecturer portal in the same work window wherever a contract changes.

## Acceptance criteria

- [x] Lecturer dashboard, timetable, roster, student reference, attendance, assessment, and gradebook endpoints use module-owned paths and `ApiResponse`.
- [x] Lecturer Access Grant, Faculty eligibility, Instructor Assignment, and Course Offering authorization remain distinct and correctly enforced.
- [x] Existing snake_case fields, envelopes, pagination, error codes, and supported route contracts remain compatible unless separately approved.
- [x] Matching lecturer portal types, composables, stores, and pages are updated for every approved contract change.
- [x] Lecturer API tests plus portal lint, typecheck, and build pass or record a reproducible environmental blocker.

## Blocked by

- [Migrate Identity & Access administration](04-migrate-identity-access-administration.md)
- [Migrate Course Offering setup and roster operations](09-migrate-course-offering-setup-roster.md)
- [Migrate class sessions and attendance operations](10-migrate-class-sessions-attendance.md)
- [Migrate assessment, gradebook and Course Result](11-migrate-assessment-gradebook-course-result.md)
- [Migrate Notification and email delivery operations](15-migrate-notification-email-delivery.md)

## Completion notes

- Moved lecturer Academic Delivery controllers and supporting services under
  `app/Modules/Academic/Delivery`, preserving existing route names and paths.
- Added lecturer-scoped Notification V2 list, mark-read, and mark-all-read API
  endpoints with shared reader/writer contracts and recipient/campus scoping.
- Added the shared `LecturerTeachingActor` and `ActiveLecturerReader` contracts
  so Delivery does not depend directly on Faculty Workforce persistence.
- Updated the lecturer portal roster filters and API types for backend field
  names, nullable inactive attendance values, optional auth relations, grade
  matrix nullability, and notification payloads.
- Updated canonical lecturer API documentation and added route ownership and
  notification contract tests.

## Verification

- `./scripts/dev.sh composer dump-autoload` passed.
- Focused lecturer/API/architecture suite: **48 passed, 290 assertions**.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed.
- `./scripts/check-docs.sh` passed: 101 Markdown files, 46 ADRs.
- `pnpm typecheck` passed; `pnpm build` passed with existing duplicate-component
  and browser-data warnings.
- `pnpm lint` is blocked reproducibly because the nested portal lacks
  `FE/lecturer-nuxt/pnpm-workspace.yaml`, required by `eslint-plugin-pnpm`.
- `migration-debt:inventory --check --format=table` remains blocked by the
  pre-existing approved `shared_model_imports` baseline: 596 current vs 568;
  the baseline was not changed as part of this cut-over.
- Repository-wide PHP tests and global frontend checks were not run; scoped
  checks were used per repository guidance.
