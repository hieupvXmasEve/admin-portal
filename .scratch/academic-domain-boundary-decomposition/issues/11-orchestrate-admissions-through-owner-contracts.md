# Orchestrate Admissions Approve and Revoke through owner contracts

Status: ready-for-human

Portal impact: student

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Keep Admissions as the owner of Approve and Revoke while replacing direct cross-context model creation with synchronous commands to Student Registry, Identity & Access, and Academic Progression & Lifecycle. Approve must atomically create Student Identity, every Student–Guardian Relationship, account/access state, and Program Enrollment; Revoke must use the same boundaries and existing downstream-activity guard.

## Acceptance criteria

- [x] Approve invokes owner commands and commits the Application transition plus all required context outcomes in one database transaction.
- [x] Any Registry, Identity, guardian, or Program Enrollment failure rolls back every Approve write and leaves the Application pending.
- [x] All Applicant Guardians are preserved even when no account can be created.
- [x] Revoke removes or reverses only the owner-managed records allowed by the existing safe-window rule.
- [x] Revoke is blocked without data loss when downstream academic or financial activity exists.
- [x] Existing staff permissions, campus scoping, audit facts, routes, and response behavior remain compatible.
- [ ] Admissions lifecycle, rollback, idempotency, architecture, and student portal checks pass.

## Blocked by

- [Issue 09: Separate Student–Guardian Relationship from Guardian Access Grant](09-separate-guardian-relationship-from-access.md)
- [Issue 10: Materialize Program Enrollment and separate its state machines](10-materialize-program-enrollment.md)

## Verification

- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/StudentApplication tests/Feature/Registry/GuardianRelationshipBackfillTest.php tests/Feature/Identity/GuardianAccessGrantTest.php tests/Feature/Academic/ProgramEnrollmentMaterializationTest.php tests/Feature/Architecture/AdmissionsOwnerContractsArchTest.php` (86 tests, 438 assertions).
- Passed: focused owner-boundary architecture tests (7 tests, 34 assertions), Pint, and `git diff --check`.
- Passed: student portal repository inspection; no API contract or portal source changed, and its nested repository is clean.
- Blocked: `cd FE/student-nuxt && pnpm lint` exits before linting because `eslint-plugin-pnpm` requires a missing `pnpm-workspace.yaml`.
- Blocked by environment: repository Vue type-check reaches the Node heap limit; `./scripts/dev.sh test` exits 255 with no diagnostics. The complete Architecture directory retains an unrelated DNG reservation-lifecycle failure in `MaterializerOnlyFinanceChargeArchTest`.

## Comments

- 2026-07-18: Refactored Admissions Approve/Revoke through Registry, Identity, Guardian, Progression, and Finance owner contracts. Approval is atomic; rollback tests cover Identity, Registry, Guardian, and Program Enrollment failures. Revoke preserves progressed enrollments and blocks both academic and financial activity. Two-axis review findings were resolved. Held for human verification only because the remaining required portal/repository-wide gates are blocked by existing environment or baseline failures.
