# Move DNG Campus Mapping into Finance configuration

Status: ready-for-human

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Move the mapping between an Institution Campus and DNG's external campus code into Finance-owned integration configuration. Backfill current mappings, provide a Finance administration surface, cut DNG creation/reconciliation over to the Finance mapping, and stop treating the provider code as part of Institution Campus identity.

## Acceptance criteria

- [x] Existing campus-to-DNG codes are backfilled idempotently into Finance-owned mapping records with uniqueness and audit rules.
- [x] Authorized Finance staff can view and maintain the mapping through a campus-scoped administration flow.
- [x] DNG payment request, worklist, webhook, and reconciliation behavior resolves provider codes only through Finance ownership.
- [x] Missing or duplicate mappings produce explicit actionable errors without creating partial payment requests.
- [x] Institution Campus create/edit/search contracts no longer expose or mutate DNG provider metadata.
- [x] The legacy Campus provider field has no runtime readers or writers and is left only for an explicitly reversible cleanup step.
- [ ] Existing student payment behavior, Finance tests, migration tests, and student portal checks pass.

## Blocked by

- [Issue 02: Own Institution Campus and Department through the Institution admin flow](02-own-campus-and-department-through-institution.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng/DngCampusMappingTest.php tests/Feature/Finance/Dng/ReconcileDngPaymentsJobTest.php tests/Feature/Institution/InstitutionBoundaryTest.php tests/Feature/Architecture/InstitutionBoundaryArchTest.php`
- `./scripts/dev.sh npm run type-check`
- `./scripts/dev.sh composer exec pint -- --dirty --format agent`
- `./scripts/portal-status.sh` (both portals clean; no portal contract changed)
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng/DngCampusMappingTest.php tests/Feature/Finance/Dng/ReconcileDngPaymentsJobTest.php tests/Feature/Institution/InstitutionBoundaryTest.php tests/Feature/Architecture/InstitutionBoundaryArchTest.php` (13 passed, 77 assertions)
- `./scripts/dev.sh npm exec prettier --check` and `./scripts/dev.sh npm exec eslint` for the changed frontend files

## Comments

- 2026-07-18: Finance now owns DNG campus mappings and administration. The legacy `campuses.dng_code` column is a migration-backfill source only and is hidden from runtime Campus contracts pending a separately approved cleanup migration.
- 2026-07-18: The broader Finance run is blocked by an existing `RetakeResitHqWorklistTest` failure (its expected row is absent before DNG code resolution is reached); the full Unit suite also has an unrelated `SettlementServicePriorityOrderTest` failure (`settlement_position.missing_currency`). These are outside this mapping slice, so the final verification criterion remains open for human triage.
