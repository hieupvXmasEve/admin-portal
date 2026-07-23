# Secure and migrate System Configuration

Status: completed

Portal impact: none

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Move System Configuration reads and mutations out of frozen controllers/services into an explicit platform owner boundary, close the known public mutation exposure, and preserve legitimate staff configuration behavior through authorized web/API contracts.

## Acceptance criteria

- [x] System Configuration has one explicit owner with module routes, requests, use cases, and API envelopes.
- [x] Mutations require the approved authentication, authorization, and campus/global scope; public mutation access is impossible.
- [x] Supported staff UI/API behavior, validation, configuration keys, caching, and audit evidence remain compatible.
- [x] Read access that must remain public is narrowly enumerated and tested rather than sharing mutation routes.
- [x] Legacy configuration services/controllers/routes have zero supported callers before retirement.

## Blocked by

- [Establish the Migration Debt inventory and regression guards](03-establish-migration-debt-inventory-and-guards.md)
- [Migrate Identity & Access administration](04-migrate-identity-access-administration.md)

## Completion notes

- Platform owns the `SystemConfigurationStore`, public/staff queries, update and upload Actions, FormRequests, API/web controllers, and module routes. The existing `/api/system-config*` and `/systems/config` URLs and route names are retained.
- Public reads now enumerate only approved branding/survey fields. `PUT /api/system-config` and `POST /api/system-config/upload` require `web` authentication, a selected campus, and `manage_system_config`; the global configuration is never campus-partitioned, while the selected campus is retained as authorization/audit context.
- `view_system_config` controls the staff page; view-only staff see the configuration without enabled mutation controls. Updates and uploads create Spatie activity evidence with global scope, actor, and changed keys. Uploads retain a configured safe `/storage/...` branding target.
- Retired: legacy SystemConfig service, both legacy SystemConfig controllers, update request, root API route definitions, and legacy web system-config route. Existing configuration consumers now use the Platform reader/writer contracts; the dormant survey-settings source is also constrained by the new view/manage configuration permissions.
- No production data mutation was run. Deployments must synchronize the two new permission definitions using the established permission-sync/release workflow before assigning them to non-super-admin staff.

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Platform/SystemConfigurationMigrationTest.php tests/Feature/Architecture/SystemConfigurationBoundaryArchTest.php` — 7 passed, 51 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed; frozen service/controller and direct JSON/validation debt counts decreased.
- `./scripts/dev.sh npm run type-check` — passed.
- `./scripts/dev.sh npm run lint` — passed.
- `./scripts/dev.sh composer exec pint -- --format agent <changed PHP paths>` — passed.
- `./scripts/dev.sh artisan route:list --name=system.config --json --except-vendor` — confirms the preserved staff URL is served by Platform with `web`, auth, verification, selected-campus, and `view_system_config` middleware.
- `./scripts/dev.sh test --compact` — invoked; the runner emitted broad passing progress but closed without a terminal summary, consistent with the repository's known full-suite reliability/memory baseline. The focused suites above completed cleanly.
- `./scripts/dev.sh npm run format:check` — remains blocked by pre-existing formatting warnings across unrelated `resources/` files; the changed page passed lint and type-check.

## Comments

- 2026-07-23: The required standards/spec review found and resolved the missing module-web middleware, survey-settings authorization/boundary bypass, configured branding upload-path regression, direct concrete Platform import, and Action input-shape mismatch. Final review reported no remaining issue in the migrated seams.
