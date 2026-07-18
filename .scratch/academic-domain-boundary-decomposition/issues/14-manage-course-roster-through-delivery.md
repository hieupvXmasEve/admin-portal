# Manage Course Registration and roster through Course Delivery

Status: ready-for-human

Portal impact: both

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Make Course Delivery & Assessment the owner of Course Registration and Course Offering roster participation. Cut staff enrollment, section movement, active-roster queries, and student/lecturer roster visibility over to Delivery application boundaries while keeping Program Enrollment distinct and preserving existing public contracts.

## Acceptance criteria

- [x] Course Registration represents participation in one Course Offering and is not used as Program Enrollment.
- [x] Staff add/remove/move-section flows execute through Delivery-owned commands and enforce offering, duplicate, capacity, and status rules.
- [x] Delivery obtains Student identity through Student Reference and catalog/faculty facts through their owner contracts.
- [ ] Student and lecturer roster reads retain current authorization, campus scoping, ordering, and response shapes.
- [x] Moving a Student preserves or intentionally migrates dependent Delivery evidence atomically according to existing behavior.
- [ ] Architecture tests reject new direct roster writes outside Delivery ownership.
- [ ] Staff, student portal, lecturer portal, rollback, and concurrency tests pass.

## Verification

- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php tests/Feature/Architecture/CourseRosterDeliveryBoundaryArchTest.php` — 9 tests, 24 assertions.
- Passed: `./scripts/dev.sh test`, `./scripts/dev.sh npm run type-check`, Pint on changed PHP files, and `git diff --check`.
- Passed with existing Nuxt warnings: student and lecturer portal `pnpm lint`; student portal `pnpm typecheck` and `pnpm build`; lecturer portal `pnpm typecheck` and `pnpm build`.
- Existing unrelated gap: `tests/Feature/CourseOffering` has seven Inertia 409 failures in score-tab tests despite the focused roster suite passing.
- Follow-up required: migrate the remaining Student and Lecturer service roster reads to `CourseRosterReader`, broaden the architecture test beyond the routed staff controllers, and add staff HTTP plus concurrent-enrollment coverage.

## Comments

- 2026-07-18: Delivery now owns staff roster enrollment, removal, status changes, and section movement. Commands use Student Registry and Catalog contracts, lock offering/registration rows, and preserve section-move academic-record evidence atomically. The issue remains ready for human verification because the portal read cutover and broader concurrency/HTTP coverage are incomplete.

## Blocked by

- [Issue 04: Create Course Offerings from Catalog-owned curriculum and period references](04-create-offerings-from-catalog-references.md)
- [Issue 07: Introduce Student Registry through the Finance Student overview](07-introduce-student-registry-through-finance-overview.md)
- [Issue 13: Combine Teaching Eligibility with Instructor Assignment](13-combine-teaching-eligibility-with-assignment.md)
