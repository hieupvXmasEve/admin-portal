# Own Institution Campus and Department through the Institution admin flow

Status: completed

Portal impact: none

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Establish Institution & Organization as the owner of Institution Campus and Department while preserving the existing staff administration URLs and behavior. Provide neutral Campus and Department references for downstream contexts and cut one existing Academic administration/selection flow over to the new ownership boundary as the end-to-end tracer.

## Acceptance criteria

- [x] Institution & Organization owns validation and write rules for Institution Campus and Department.
- [x] Existing campus and department list, create, edit, selection, authorization, and campus-scoping behavior remains compatible.
- [x] A neutral Campus/Department reference contract serves at least one real downstream Academic flow without exposing owning persistence.
- [x] DNG provider metadata and Building/Room behavior are explicitly left to issues 05 and 06 rather than absorbed into Institution.
- [x] Architecture tests prevent new Academic, Finance, or Facilities writes to Institution-owned records outside owner contracts.
- [x] Existing staff behavior tests and new boundary tests pass.

## Blocked by

None - can start immediately.

## Verification

- ./scripts/dev.sh artisan test --compact tests/Feature/Institution/InstitutionBoundaryTest.php tests/Feature/Architecture/InstitutionBoundaryArchTest.php
- ./scripts/dev.sh npm run type-check
- ./scripts/dev.sh npm run lint
- ./scripts/dev.sh npm run format:check
- ./scripts/dev.sh composer exec pint -- --dirty --format agent
- ./scripts/dev.sh test

## Comments

- 2026-07-18: Campus and Department administration routes now delegate to the Institution module. Academic lifecycle campus selection consumes CampusReferenceReader; building detail remains an Academic compatibility surface pending issue 06. The legacy DNG column is written only through a Finance-owned transitional contract pending issue 05.
