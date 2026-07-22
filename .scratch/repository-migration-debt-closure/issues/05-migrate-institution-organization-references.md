# Migrate Institution & Organization reference management

Status: completed

Portal impact: none

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Make Institution & Organization the supported owner of Institution Campus, Department, and organization reference workflows. Staff management surfaces and downstream consumers must use neutral references or owner contracts without changing current identifiers or user-visible behavior.

## Acceptance criteria

- [x] Campus and Department management flows are owned end to end by Institution & Organization.
- [x] Downstream contexts consume neutral references or narrow owner contracts rather than Institution persistence.
- [x] Public routes, permissions, form behavior, filters, and identifiers remain compatible.
- [x] Provider-specific identifiers are not reintroduced into Institution-owned reference data.
- [x] Legacy reference-management paths have zero supported callers before approved retirement.

## Blocked by

- [Establish the Migration Debt inventory and regression guards](03-establish-migration-debt-inventory-and-guards.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Institution/InstitutionBoundaryTest.php tests/Feature/Architecture/InstitutionBoundaryArchTest.php` — 8 passed, 49 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- `./scripts/dev.sh npm run type-check` — passed.
- `./scripts/dev.sh test` — attempted and stopped after widespread pre-existing failures in unrelated Finance, AI, and Academic suites. The focused Institution coverage remained green.

## Comments

- 2026-07-22: Retired the unused `app/Actions/Campus/*` action path under this issue's approved implementation. A runtime inventory over `app/`, `routes/`, `config/`, and `database/` found zero `App\\Actions\\Campus` consumers before deletion; an architecture regression test now enforces both that condition and the absence of legacy action files. Institution-owned management routes, neutral reference readers, and DNG provider isolation were already established and are covered by the focused Institution boundary suite.
