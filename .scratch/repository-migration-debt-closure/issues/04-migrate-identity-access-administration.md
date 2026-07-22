# Migrate Identity & Access administration

Status: completed

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Cut staff account, role, permission, impersonation, and access-grant administration over to Identity & Access ownership while preserving authentication, authorization, audit evidence, public routes, and actor behavior end to end.

## Acceptance criteria

- [x] Supported account, role, permission, and impersonation entry points are owned by Identity module controllers, requests, Actions, Queries, and routes.
- [x] Account Status and access grants remain separate from Student and Faculty business lifecycle state.
- [x] Existing route names, permissions, validation messages, redirects, API envelopes, and audit records remain compatible.
- [x] Legacy Identity-related services/controllers/routes are retired only after all runtime callers cut over.
- [x] Staff, student, guardian, and lecturer authentication/authorization regression suites pass.

## Blocked by

- [Establish the Migration Debt inventory and regression guards](03-establish-migration-debt-inventory-and-guards.md)

## Completion notes

- Identity now owns the staff account, user-role synchronization, role administration, impersonation HTTP entrypoints, requests, actions, queries, and route registration. Role persistence remains auditable through the existing `Role` model behind an Identity-owned store contract.
- Student Registry and Faculty Workforce own the profile lifecycle decisions and token issuance behind typed contracts. Identity only orchestrates the request and its account/access administration concern; it does not query Student or Faculty persistence directly.
- The established URLs, route names, per-route permission middleware, redirect destinations, role audit events, API response envelopes, and impersonation audit logs were preserved. No schema or data mutation was made.
- Retired after static runtime-caller verification: the legacy role controller, role/user requests, role routes, role/user services, and student/lecturer impersonation controllers and requests. `PermissionService` remains shared authorization infrastructure because it has live callers outside this migration scope.
- Portal impact was assessed as both. `./scripts/portal-status.sh` found clean student and lecturer portal repositories; no public `api/v1/student/*` or `api/v1/lecturer/*` contract changed, so no portal source change was needed.
- Rollback is the implementation commit: restoring it reinstates the retired route/controller/service entrypoints without a data rollback.

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Identity/AccessAdministrationMigrationTest.php tests/Feature/Identity/StudentLifecyclePortalCompatibilityTest.php tests/Feature/Identity/GuardianAccessGrantTest.php tests/Feature/Identity/LecturerAccessGrantTest.php tests/Feature/Architecture/IdentityLecturerAccessBoundaryTest.php tests/Feature/Architecture/GuardianOwnershipBoundaryArchTest.php` — 27 passed, 115 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- `./scripts/dev.sh npm run type-check` — passed.
- `./scripts/dev.sh test` — invoked for the full project regression suite.
