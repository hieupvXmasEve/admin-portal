# Manage attendance and gradebook through Course Delivery

Status: ready-for-human

Portal impact: both

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Complete the operational Course Delivery tracer by placing sessions, attendance, assessment components, lecturer gradebook edits, Canvas grade sync, readiness blockers, and previews behind Delivery-owned application boundaries. Student and lecturer surfaces keep their current behavior while other contexts stop deriving results from Delivery internals.

## Acceptance criteria

- [ ] Delivery owns sessions, attendance evidence, assessment components, component scores, Canvas sync, and grading-rule inputs.
- [x] Only active eligible roster members can receive attendance or assessment evidence according to existing rules.
- [ ] Lecturer gradebook and attendance APIs retain authorization, response shapes, validation, and campus/offering scoping.
- [x] Sync preview remains side-effect free, and apply reads fresh Canvas data before committing.
- [x] Readiness blockers are supplied by the backend Delivery boundary rather than inferred by frontend code.
- [x] No Progression consumer reads component-score or attendance persistence directly to create transcript outcomes.
- [ ] Existing staff, student portal, lecturer portal, Canvas, attendance, and gradebook tests pass with new architecture guards.

## Blocked by

- [Issue 14: Manage Course Registration and roster through Course Delivery](14-manage-course-roster-through-delivery.md)

## Verification

- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/CourseDeliveryAssessmentBoundaryArchTest.php tests/Feature/CourseOffering/CourseOfferingRecordAttendanceTest.php` — 10 tests, 26 assertions.
- Passed: focused Delivery, attendance, operational-state, Canvas, and recalculation run — 41 tests, 314 assertions; one unrelated `CourseOfferingOperationalStateTest` factory sequence collision prevented a fully green aggregate run.
- Passed: Pint, `NODE_OPTIONS=--max-old-space-size=4096 ./scripts/dev.sh npm run type-check`, and `git diff --check`.
- Portal checks: student and lecturer `pnpm typecheck`/`pnpm build` completed with existing Nuxt duplicate-import warnings. Both `pnpm lint` commands fail before linting because `eslint-plugin-pnpm` cannot find a `pnpm-workspace.yaml`.
- Known verification gap: `LecturerAttendanceMarkApiTest` and `LecturerGradebookApiTest` currently receive `403 Actor is not authorized for this API surface` from actor middleware before reaching their controllers; the refactor does not alter this middleware. The existing gradebook test also has an unrelated invalid class-session factory time failure.

## Comments

- 2026-07-18: Moved the operational attendance, lecturer-attendance, gradebook, Canvas-sync, and cockpit-readiness implementations into `app/Modules/Academic/Delivery`. Old namespaces now provide compatibility adapters only. Lecturer gradebook offering ownership is enforced through `CourseOfferingPolicy`; response shapes and endpoints are unchanged. Delivery architecture tests prohibit legacy implementation imports and Progression reads of attendance/component-score persistence. Remaining scope is the session/assessment-component management and grading-rule input cutover, plus the pre-existing API actor-middleware, factory, and portal-lint verification gaps.
