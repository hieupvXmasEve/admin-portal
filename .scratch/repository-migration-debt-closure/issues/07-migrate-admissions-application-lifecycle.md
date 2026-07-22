# Migrate the Admissions application lifecycle

Status: completed

Portal impact: student

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Move Application, Applicant Guardian, admission-document, ingestion, Approve, Reject, and Revoke workflows behind Admissions ownership. Cross-context changes must use owner commands inside the accepted atomic orchestration while preserving existing staff and integration contracts.

## Acceptance criteria

- [x] Admissions owns application lifecycle authorization and orchestration without directly persisting another context's models.
- [x] Approve and Revoke preserve atomic rollback across Registry, Identity, and Academic Progression owner commands.
- [x] Frozen application and downstream-activity protections remain unchanged.
- [x] Staff routes, validation, CRM ingestion envelopes, documents, and audit evidence remain compatible.
- [x] Any legacy application-data backfill or column cleanup requires a separately approved data checkpoint.

## Blocked by

- [Migrate Identity & Access administration](04-migrate-identity-access-administration.md)
- [Migrate Institution & Organization reference management](05-migrate-institution-organization-references.md)
- [Migrate Student Registry identity and Guardian relationships](06-migrate-student-registry-identity-guardians.md)

## Delivery notes

- Admissions now owns the registered web/API routes, lifecycle policy, CRM request boundary, document checklist, guardian mutations, and lifecycle Actions. The CRM endpoint remains `/api/v1/admissions/*` and preserves the `ApiResponse` envelope.
- Approval and revoke use Registry, Identity, Academic, and Finance owner contracts inside the existing transaction boundary. No data backfill or schema/data cleanup was run.
- Portal impact remains `student`; no student API request/response contract changed, so no portal source change was needed.

## Verification

- `./scripts/dev.sh composer exec pint -- --dirty --format agent`
- `./scripts/dev.sh artisan test --compact tests/Feature/StudentApplication/StaffLifecycleTest.php tests/Feature/StudentApplication/GuardiansTest.php tests/Feature/StudentApplication/AuthorizationTest.php tests/Feature/Admissions/IngestionApplicationsTest.php tests/Feature/Architecture/AdmissionsOwnerContractsArchTest.php` — 63 passed, 289 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed.
- `./scripts/dev.sh test` was attempted; it stopped when the test container exhausted its configured 256 MiB PHP memory limit in `brick/math` during the broader suite, after the targeted coverage above had passed.
