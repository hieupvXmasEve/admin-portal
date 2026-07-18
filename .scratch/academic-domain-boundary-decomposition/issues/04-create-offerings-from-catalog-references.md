# Create Course Offerings from Catalog-owned curriculum and period references

Status: ready-for-human

Portal impact: lecturer

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Use Course Offering creation and editing as the tracer between Academic Catalog & Calendar and Course Delivery & Assessment. Catalog owns Program, Curriculum Version, Curriculum Module, Unit, syllabus, and Academic Period facts; Delivery accepts stable references and owns the operational Course Offering without querying Catalog persistence directly.

## Acceptance criteria

- [x] Course Offering create/edit obtains allowed catalog choices and validates references through Catalog-owned contracts.
- [x] Catalog remains the source of truth for Program, Curriculum Version, Unit, syllabus, and Academic Period semantics.
- [x] Delivery owns Course Offering status and operational configuration without taking ownership of catalog records.
- [x] Existing staff routes, forms, validation messages, and created offering behavior remain compatible.
- [ ] Existing lecturer-facing offering contracts remain compatible and lecturer portal checks pass.
- [x] Architecture tests prevent Delivery from adding new direct Catalog persistence dependencies.
- [x] Focused offering creation/edit and invalid-reference tests pass.

## Blocked by

- [Issue 02: Own Institution Campus and Department through the Institution admin flow](02-own-campus-and-department-through-institution.md)
- [Issue 03: Resolve Current Academic Period through Academic Catalog & Calendar](03-resolve-current-academic-period-through-catalog.md)

## Verification

- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/CourseOffering/CourseOfferingCatalogReferenceTest.php tests/Feature/CourseOffering/CanvasSyllabusTemplateGuardTest.php tests/Feature/Architecture/CourseOfferingCatalogBoundaryArchTest.php` (12 tests, 67 assertions).
- Passed: `./scripts/dev.sh composer exec pint -- --dirty --format agent` and `git diff --check`.
- Passed: `FE/lecturer-nuxt`: `pnpm typecheck` and `pnpm build` (warnings only); no portal source or API contract changed.
- Blocked by pre-existing repository verification failures: `./scripts/dev.sh test` exits 255 without diagnostics; root `npm run type-check` has existing project-wide errors (and needs a larger Node heap to finish); lecturer `pnpm lint` cannot resolve its required `pnpm-workspace.yaml`.

## Comments

- 2026-07-18: Added the Catalog reader contract and implementation, moved only the create/edit offering routes to a Delivery-facing module controller, and retained routes, validation messages, and Canvas syllabus safeguards. The issue remains ready for human verification because the required full-suite and lint checks cannot complete in the current repository state.
