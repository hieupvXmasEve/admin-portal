# Migrate Events and Clubs operations

Status: completed

Portal impact: student

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Give Event and Club management, membership, participation, check-in, reporting, student access, and notifications one explicit owner boundary and migrate their supported staff/student workflows end to end.

## Acceptance criteria

- [x] Event and Club ownership is explicit and supported commands/queries no longer live in frozen services/controllers.
- [x] Membership, participation, check-in, authorization, campus scope, reporting, and notification behavior remain compatible.
- [x] Staff UI and student API surfaces use the owner boundary and current frontend/API conventions.
- [x] Student identity and notification interactions use approved contracts rather than shared business-model coupling.
- [x] Legacy Event/Club paths have zero supported callers before retirement.

## Implementation notes

- Engagement now owns Event/Club actions, reporting query, controllers, requests, routes, queue job, and console commands. Existing queued `App\\Jobs\\ProcessEventNotificationJob` payloads remain loadable through a temporary compatibility subclass.
- Student portal routes, auth middleware, names, and response resources are unchanged, so no portal source change was required.
- Review follow-up fixed the missing Campus import in Event operations and removed retired Event/Club service paths from the migration-debt inventory.
- Manual participant lookup, eligibility, campus filtering, and availability now use `StudentReferenceReader` plus `StudentLifecycleStatusReader`; Engagement no longer imports or queries the shared Student model.
- Admin Event check-in and participant endpoints now use FormRequests and `ApiResponse` envelopes while retaining their response data and HTTP behavior.

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/EngagementOwnershipBoundaryTest.php tests/Unit/Notification/EventNotificationServiceTest.php` — pass (4 tests, 33 assertions)
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — pass
- `./scripts/dev.sh npm run type-check` — pass
- `./scripts/dev.sh npm run lint` — pass
- `./scripts/dev.sh npm run format:check` — pass
- `./scripts/dev.sh test` — unrelated failures in `StudentAcademicSummaryScoresSchemeDisplayTest` and `GradeBreakdownApiTest`; Events/Clubs checks pass.
- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/EngagementOwnershipBoundaryTest.php tests/Unit/Notification/EventNotificationServiceTest.php` — pass (5 tests, 42 assertions after the boundary follow-up)

## Blocked by

- [Migrate Identity & Access administration](04-migrate-identity-access-administration.md)
- [Migrate Student Registry identity and Guardian relationships](06-migrate-student-registry-identity-guardians.md)
- [Migrate Notification and email delivery operations](15-migrate-notification-email-delivery.md)
