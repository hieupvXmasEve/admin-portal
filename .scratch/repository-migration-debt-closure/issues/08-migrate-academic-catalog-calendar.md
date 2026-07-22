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
- Remaining work: Curriculum Version, Curriculum Unit/grouping, Unit, syllabus, Academic Period administration and Campus Period Schedule workflows; their import/export behavior; downstream Delivery, Progression, Finance, report, and portal caller inventory/cutover; and the approved legacy removals. The acceptance criteria therefore remain open and this issue stays `ready-for-agent`.
