# Migrate Academic Catalog & Calendar management

Status: ready-for-agent

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Cut Program, Curriculum Version, Unit, curriculum grouping, syllabus, and Academic Period management over to Academic Catalog & Calendar ownership, including staff UI, import/export, validation, and downstream reference contracts.

## Acceptance criteria

- [ ] Catalog and Academic Period workflows are owned by the accepted context through module routes, controllers, requests, Actions, and Queries.
- [ ] One institution-wide Academic Period identity is preserved; campus differences use schedule overlays rather than duplicate identities.
- [ ] Imports/exports preserve supported templates, validation, identifiers, and audit behavior.
- [ ] Delivery, Progression, Finance, portals, and reports consume catalog references through approved boundaries.
- [ ] Legacy catalog/calendar callers are characterized and cut over before removal.

## Blocked by

- [Migrate Institution & Organization reference management](05-migrate-institution-organization-references.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Academic/Catalog/ProgramManagementTest.php tests/Feature/Architecture/AcademicCatalogManagementBoundaryArchTest.php tests/Feature/Finance/Shell/SemesterContextTest.php tests/Feature/AcademicPeriodCatalogBoundaryTest.php` — passed: 12 tests, 87 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed; no approved debt baseline increased.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- `./scripts/dev.sh npm run type-check` — passed.
- `./scripts/dev.sh test` — invoked twice; the container runner did not return a final summary or exit status. Focused coverage above is green.
- `./scripts/dev.sh npm run lint` — stopped after the repository ESLint step before format checking, an existing workspace-level verification gap.

## Comments

- 2026-07-22: Program staff management now routes through Academic Catalog with owner FormRequests, Actions, Queries, policy checks, and module routes while preserving `programs.*` names, URLs, permissions, Inertia page paths/props, validation messages, audit-model behavior, and cache invalidation. The root `semester-context` and existing `finance/semester-context` URLs are now Catalog-owned selected-period endpoints; the latter is registered by Catalog so Finance does not import Catalog internals. The previously routable Program controller/service are retained only as explicitly deprecated compatibility code pending the separate approved removal gate. This slice also confines the shared `Program` persistence allowlist to the nested Catalog owner so other Academic contexts remain visible to the migration inventory.
- 2026-07-23: Academic Period administration routes (`semesters.*` and the existing activation/status JSON endpoints) now resolve to Catalog-owned FormRequests, Actions, Queries, policies, and controller. The legacy split route retains only semester enrollment endpoints, which belong to the separate Course Delivery slice. The supported list/create contract is characterized by `AcademicPeriodManagementTest`, but execution is currently blocked because the shared `db_test` database has an inconsistent migration state; no database reset was attempted.
- 2026-07-23: Unit CRUD, search, code validation, relationship reads, and guarded bulk deletion now resolve to Catalog-owned routes, FormRequests, Actions, and Queries. Unit import/export and prerequisite-expression validation still use explicit legacy compatibility endpoints while their template/audit flow is characterized. Curriculum Unit CRUD/API and Curriculum Module grouping routes now also resolve to Catalog-owned FormRequests, Actions, Queries, and controllers. Existing URLs, route names, Inertia page paths, and generic permission gates were preserved. Focused tests for the Unit route contract were added, but cannot execute until `db_test` is repaired because its migrations attempt to recreate already-present tables.
- Remaining work: Curriculum Version, syllabus, and Campus Period Schedule workflows; Unit and curriculum import/export ownership; downstream Delivery, Progression, Finance, report, and portal caller inventory/cutover; and the approved legacy removals. The acceptance criteria therefore remain open and this issue stays `ready-for-agent`.
