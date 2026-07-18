# Manage attendance and gradebook through Course Delivery

Status: ready-for-human

Portal impact: both

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Complete the operational Course Delivery tracer by placing sessions, attendance, assessment components, lecturer gradebook edits, Canvas grade sync, readiness blockers, and previews behind Delivery-owned application boundaries. Student and lecturer surfaces keep their current behavior while other contexts stop deriving results from Delivery internals.

## Acceptance criteria

- [x] Delivery owns sessions, attendance evidence, assessment components, component scores, Canvas sync, and grading-rule inputs.
- [x] Only active eligible roster members can receive attendance or assessment evidence according to existing rules.
- [x] Lecturer gradebook and attendance APIs retain authorization, response shapes, validation, and campus/offering scoping.
- [x] Sync preview remains side-effect free, and apply reads fresh Canvas data before committing.
- [x] Readiness blockers are supplied by the backend Delivery boundary rather than inferred by frontend code.
- [x] No Progression consumer reads component-score or attendance persistence directly to create transcript outcomes.
- [ ] Existing staff, student portal, lecturer portal, Canvas, attendance, and gradebook tests pass with new architecture guards. (Portal lint remains blocked by workspace configuration.)

## Blocked by

- [Issue 14: Manage Course Registration and roster through Course Delivery](14-manage-course-roster-through-delivery.md)

## Verification

- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/CourseDeliveryAssessmentBoundaryArchTest.php tests/Feature/CourseOffering/CourseOfferingRecordAttendanceTest.php` — 10 tests, 26 assertions.
- Passed: focused Delivery, attendance, operational-state, Canvas, recalculation, lecturer assessment, attendance, and gradebook run — 73 tests, 501 assertions.
- Passed: Pint, `NODE_OPTIONS=--max-old-space-size=4096 ./scripts/dev.sh npm run type-check`, and `git diff --check`.
- Portal checks: student and lecturer `pnpm typecheck`/`pnpm build` completed with existing Nuxt duplicate-import warnings. Both `pnpm lint` commands fail before linting because `eslint-plugin-pnpm` cannot find a `pnpm-workspace.yaml`.
- The Docker wrapper's unfiltered `artisan test --compact` currently returns exit code 0 without enumerating tests, so the explicit focused command above is the auditable backend result.

## Comments

- 2026-07-18: Moved the operational attendance, lecturer-attendance, gradebook, Canvas-sync, cockpit-readiness, class-session, assessment-component, and assessment-weight implementations into `app/Modules/Academic/Delivery`. Old namespaces now provide compatibility adapters only. Lecturer gradebook offering ownership is enforced through `CourseOfferingPolicy`; response shapes and endpoints are unchanged. Delivery architecture tests prohibit legacy implementation imports and Progression reads of attendance/component-score persistence.
- 2026-07-18: Updated lecturer API fixtures to create an active lecturer `User` linked to its `Lecture`, which creates the required access grant and allows `api.actor:lecturer` to authorize the request. Fixed valid class-session times and unique session sequencing in the affected test fixtures. Explicit Delivery regressions pass; portal lint remains an environment configuration gap because `eslint-plugin-pnpm` cannot find `pnpm-workspace.yaml`.
