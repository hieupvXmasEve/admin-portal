# Separate Student–Guardian Relationship from Guardian Access Grant

Status: ready-for-human

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Make Student Registry the durable owner of every Student–Guardian Relationship and Identity & Access the owner of optional Guardian Access Grant. Preserve Guardians without email/account, separate relationship and primary-contact facts from portal access level, and keep current guardian authentication and student-context behavior compatible.

## Acceptance criteria

- [x] Registry stores every Guardian relationship, relationship type, and primary designation independently of account availability.
- [x] Identity stores and evaluates Guardian Access Grant separately from the Registry relationship.
- [x] Creating, changing, or revoking portal access never creates, changes, or deletes the Student–Guardian Relationship.
- [x] Guardians without email or account remain visible to authorized staff and are not offered an unusable access grant.
- [x] Guardian login and allowed Student context read Identity-owned access state rather than relationship metadata alone.
- [ ] Existing authorization, campus scoping, parent-facing behavior, and student portal checks pass.
- [x] Migration/backfill and feature tests preserve existing relationships and access levels without duplicates.

## Blocked by

- [Issue 07: Introduce Student Registry through the Finance Student overview](07-introduce-student-registry-through-finance-overview.md)

## Verification

- Passed: `./scripts/dev.sh composer exec pint -- --dirty --format agent` and `git diff --check`.
- Passed: focused Identity grant/login/refresh/context, Registry migration/backfill, Admissions Guardian, Student update, staff Hub, and architecture tests.
- Passed: ESLint and Prettier checks for the changed Vue/TypeScript files.
- Passed with existing warnings: `cd FE/student-nuxt && pnpm typecheck && pnpm build`.
- Blocked: `cd FE/student-nuxt && pnpm lint` fails before linting because `eslint-plugin-pnpm` requires a missing `pnpm-workspace.yaml`.
- Blocked by environment: repository Vue typecheck reaches the default Node heap limit; larger heap attempts are killed by the container memory limit.
- Known unrelated baseline failures: the complete Unit directory has one Finance settlement fixture missing currency; Student Application revoke cases hit the existing restrictive `billing_accounts.student_id` foreign key. The no-path/Feature-directory suite discovery exits 255 without diagnostics. Repository `format:check` also reports pre-existing formatting and malformed Forms templates.

## Comments

- 2026-07-18: Added durable Registry Guardian relationships, optional Identity access grants, guarded login/refresh/context authorization, Admissions and staff-flow cutovers, and a data-preserving backfill. Two-axis review found and resolved conflicting-primary backfill, replacement-account mapping, Student/grant integrity, and limited-view rendering gaps. Held for human follow-up because the required student portal lint gate is blocked by its workspace configuration.
