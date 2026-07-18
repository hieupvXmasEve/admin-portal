# Combine Teaching Eligibility with Instructor Assignment

Status: ready-for-human

Portal impact: lecturer

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Use the Course Offering instructor-assignment flow as the tracer between Faculty Workforce and Course Delivery & Assessment. Workforce answers whether a Faculty Member is professionally and contractually eligible for the Unit; Delivery combines that answer with current workload, assignments, campus, and timetable conflicts before owning the resulting Instructor Assignment.

## Acceptance criteria

- [x] Faculty Workforce owns employee identity, employment/contract facts, qualifications, expertise, preferences, and Teaching Eligibility.
- [x] Course Delivery obtains Teaching Eligibility through a narrow query contract without reading Faculty persistence.
- [x] Instructor Assignment validates eligibility plus current Delivery workload, campus, assignment, and timetable conflicts.
- [x] Eligibility does not reserve capacity or create an assignment, and assignment does not alter employment or access state.
- [x] Existing staff assignment forms, validation behavior, lecturer course visibility, and API shapes remain compatible.
- [x] Architecture tests enforce Workforce/Delivery ownership and focused tests cover eligible, ineligible, overloaded, and conflicting assignments.
- [ ] Lecturer portal checks pass.

## Blocked by

- [Issue 04: Create Course Offerings from Catalog-owned curriculum and period references](04-create-offerings-from-catalog-references.md)
- [Issue 12: Own Lecturer Access Grant and Faculty Access Eligibility](12-own-lecturer-access-in-identity.md)

## Implementation notes

- Workforce eligibility rejects inactive, non-active employment, unavailable, not-yet-started/expired contracts, and explicit expertise mismatches. Legacy Faculty records without structured `expertise_areas` retain their historical availability-based assignment behavior until Workforce data is completed; this is a compatibility transition that needs an explicit data-completion exit before eligibility can require recorded unit expertise.
- Delivery owns the assignment write and checks campus alignment, workload, and timetable overlap. It reads Workforce only through `TeachingEligibilityReader` and Catalog only through `CourseOfferingCatalogReader`.

## Verification

- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/CourseOffering/InstructorAssignmentTest.php tests/Feature/Architecture/TeachingEligibilityAssignmentBoundaryTest.php tests/Feature/CourseOffering/CourseOfferingCatalogReferenceTest.php` — 16 tests, 50 assertions.
- Passed: `./scripts/dev.sh test` (exit 0), `./scripts/dev.sh npm run type-check`, route-list checks, Pint on the changed PHP files, and `git diff --check`.
- Lecturer portal: `pnpm typecheck` and `pnpm build` passed in `FE/lecturer-nuxt` (with existing warnings). `pnpm lint` is blocked before linting because that checkout has no `pnpm-workspace.yaml`; the required portal check therefore remains open.
- Root `./scripts/dev.sh npm exec eslint .` remains blocked by 4,782 pre-existing errors, including generated `FE/lecturer-nuxt/.nuxt` files and unrelated Vue files.
- The existing `LecturerRosterInactiveStudentTest` HTTP assertions return 403 because its fixture does not satisfy the current lecturer API authorization surface. The assignment-path test verifies visibility through `LecturerCourseService`; no lecturer API contract was changed here.
