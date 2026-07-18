# Resolve Current Academic Period through Academic Catalog & Calendar

Status: completed

Portal impact: both

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Make Academic Catalog & Calendar the owner of Academic Period identity and current-period resolution while preserving the existing Semester-backed data and public contracts. Separate the institution-wide Current Academic Period from Selected Academic Period application state, and support campus-specific operating or registration-window overrides without duplicating period identity.

## Acceptance criteria

- [x] Exactly one institution-wide Academic Period identity is used across teaching, Admissions, reporting, and Finance correlation.
- [x] Current Academic Period resolution is exposed through an owner contract rather than direct Semester queries by consumers.
- [x] Selected Academic Period changes filtering/navigation only and cannot mutate or redefine Current Academic Period.
- [x] Campus Period Schedule overrides can vary operating dates or registration windows while retaining the same Academic Period identifier.
- [x] Existing period administration, filters, API shapes, and route behavior remain compatible with the Semester-backed implementation.
- [x] Architecture tests reject new direct period persistence reads outside Academic Catalog & Calendar.
- [x] Academic, student portal, and lecturer portal period-related checks pass.

## Blocked by

- [Issue 02: Own Institution Campus and Department through the Institution admin flow](02-own-campus-and-department-through-institution.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/AcademicPeriodCatalogBoundaryTest.php tests/Feature/Architecture/AcademicPeriodBoundaryArchTest.php tests/Feature/Finance/Shell/SemesterContextTest.php tests/Feature/Api/V1/Student/TimetableControllerTest.php tests/Feature/Api/V1/Student/SemesterProgressCompletedUnitsTest.php tests/Feature/Api/V1/Lecturer/LecturerInvigilationTimetableTest.php`
- `./scripts/dev.sh npm run type-check`
- `./scripts/dev.sh npm run lint`
- `./scripts/dev.sh npm run format:check`
- `./scripts/dev.sh composer exec pint -- --dirty --format agent`
- `./scripts/dev.sh test`

## Comments

- 2026-07-18: Academic Catalog now publishes the Semester-backed `AcademicPeriodReader` contract. Selected-period session state remains separate from Current Academic Period; campus schedules override operating and registration dates without changing `semester_id`. Current-period consumers across Academic, Finance, AI, shared support, and v1 student/lecturer paths now resolve through the contract, and a boundary test prevents direct active-period reads outside Catalog.
