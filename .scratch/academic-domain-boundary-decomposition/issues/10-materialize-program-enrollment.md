# Materialize Program Enrollment and separate its state machines

Status: ready-for-human

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Introduce Program Enrollment as the history-bearing owner of a Student's Program, Curriculum Version, intake Academic Period, Program Enrollment Status, and Study Stage. Backfill existing Students, enforce at most one primary active Program Enrollment, and cut one student academic-summary flow over to the new aggregate while preserving current external contracts.

## Acceptance criteria

- [x] Existing program, curriculum, intake, enrollment-status, and study-stage facts are backfilled idempotently with audit/provenance evidence.
- [x] At most one primary active Program Enrollment is allowed per Student while historical enrollments remain queryable.
- [x] Account Status, Program Enrollment Status, and Study Stage are stored and changed independently.
- [x] A real student academic-summary or Student Hub flow reads Program Enrollment through Academic Progression & Lifecycle ownership.
- [x] Student Identity no longer acts as the source of truth for the migrated program/lifecycle facts.
- [x] Existing student API response shapes and student portal behavior remain compatible.
- [ ] Backfill, invariant, lifecycle-read, architecture, and student portal checks pass.

## Blocked by

- [Issue 03: Resolve Current Academic Period through Academic Catalog & Calendar](03-resolve-current-academic-period-through-catalog.md)
- [Issue 04: Create Course Offerings from Catalog-owned curriculum and period references](04-create-offerings-from-catalog-references.md)
- [Issue 07: Introduce Student Registry through the Finance Student overview](07-introduce-student-registry-through-finance-overview.md)

## Verification

- Passed: focused Program Enrollment, Student Hub overview/lifecycle, and architecture tests (22 tests, 182 assertions).
- Passed: root TypeScript check and PHP Pint; student portal `pnpm typecheck` and `pnpm build` (warnings only).
- Passed: two-axis implementation review (standards and issue-spec) with no remaining findings.
- Blocked outside this slice: `./scripts/dev.sh test` exits 255 without diagnostics; `FE/student-nuxt` `pnpm lint` cannot load `eslint-plugin-pnpm` because `pnpm-workspace.yaml` is absent. Root `npm run format:check` also reports pre-existing formatting across untouched `resources/` files.

## Comments

- 2026-07-18: Added the Program Enrollment aggregate, generated-column primary-active invariant, provenance-carrying idempotent backfill command, and Progression reader/writer contracts. The Student Hub overview now reads program, curriculum, intake, enrollment status, and study stage from Program Enrollment while preserving its existing response keys. The issue awaits human verification only for repository/portal checks that are blocked by existing workspace configuration.
