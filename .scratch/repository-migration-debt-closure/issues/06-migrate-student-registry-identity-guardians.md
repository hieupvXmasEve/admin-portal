# Migrate Student Registry identity and Guardian relationships

Status: ready-for-agent

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Cut Student identity, profile, contact, campus affiliation, and Student–Guardian Relationship workflows over to Student Registry ownership. Identity continues to own optional Guardian Access Grants, and downstream contexts receive Student References instead of the transitional Student God Model.

## Acceptance criteria

- [ ] Student Registry owns supported identity/profile/contact and Student–Guardian Relationship mutations and reads.
- [ ] Guardians without accounts remain durable relationships, while access revocation never deletes the relationship.
- [ ] Downstream contexts use Student References or owner contracts and do not infer Program Enrollment or Account Status from Student identity.
- [ ] Existing staff, student, and guardian routes/API contracts remain compatible.
- [ ] Any data backfill or legacy-column cleanup stops at the parent issue's approval gate before execution.

## Blocked by

- [Migrate Identity & Access administration](04-migrate-identity-access-administration.md)
- [Migrate Institution & Organization reference management](05-migrate-institution-organization-references.md)

## Comments

- 2026-07-22: Partial profile-write cutover. The supported v1 student profile update and avatar update paths now call the Student Registry `StudentProfileWriter` contract. Registry permits only identity/profile/contact attributes; it deliberately rejects program, lifecycle, and account-status fields. The backing `students` persistence remains transitional, while Identity remains the owner of account updates and Guardian Access Grants. Public routes and response envelopes were not changed. Runtime entry points examined: `Api\\V1\\Student\\ProfileController` and `ProfileService`; portal impact is student, but no request/response contract changed, so the student portal required no source change. Data impact: none — no backfill, cleanup, or data command was run. Rollback is a revert of the implementation commit; the profile endpoint and focused boundary tests are the observability signals.
- 2026-07-22: Verification: `tests/Feature/Registry/StudentProfileWriterTest.php`, `tests/Feature/Architecture/StudentRegistryProfileBoundaryArchTest.php`, Registry reference, Identity Guardian grant, and Guardian ownership suites passed (12 tests, 52 assertions). PHP Pint, `git diff --check`, root ESLint, and root TypeScript type-check passed. The migration-debt inventory guard passed without increasing any approved baseline. The repository-wide test command was invoked separately; remaining full-suite evidence must be recorded before this issue can be completed.
- Remaining scope: Registry-owned profile reads, the remaining staff and legacy API identity write paths, and their caller cutovers still require characterization and migration. Guardian relationship/access foundations already exist, but this issue cannot be marked complete until all acceptance criteria have direct runtime-caller evidence. No approval was requested or granted for data backfill or legacy-column cleanup.
- 2026-07-23: Partial profile-read cutover. The supported v1 student profile read path now obtains identity, profile, and contact data through `StudentProfileReader` from Student Registry. The snapshot deliberately includes campus affiliation only as `campus_id`; it excludes Program Enrollment, lifecycle/account status, notification preferences, and other non-Registry data. `ProfileService` continues to source program, curriculum, campus presentation, enrollment/lifecycle fields, and preferences from the transitional model while their owner contracts are characterized. `Api\\V1\\Student\\ProfileController` and `ProfileResource` were unchanged, so the student portal response envelope remains compatible and needs no source change. Data impact: none — no backfill, cleanup, or data command was run. Rollback is a revert of this cutover commit; the Registry profile reader and v1 service tests are the observability signals.
- 2026-07-23: Verification: `tests/Feature/Registry/StudentProfileReaderTest.php`, `StudentProfileWriterTest.php`, `StudentReferenceReaderTest.php`, and `StudentRegistryProfileBoundaryArchTest.php` passed (6 tests, 42 assertions). PHP Pint, `git diff --check`, and the migration-debt inventory guard passed. A repository-wide test attempt reached broad Academic coverage but stopped on the configured 256 MB PHP memory limit (`Allowed memory size of 268435456 bytes exhausted`); it produced no assertion failure attributable to this cutover. Remaining scope is now the owner-contract migration of the non-Registry fields noted above, remaining staff/legacy identity writers, and full Guardian runtime-caller evidence. The issue remains `ready-for-agent`; no acceptance criterion is marked complete.
