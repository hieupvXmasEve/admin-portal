# Cut over the Student API and student portal

Status: ready-for-agent

Portal impact: student

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Cut student and parent-proxy API workflows over from legacy controllers/services to the owning Identity, Registry, Catalog, Delivery, Progression, Finance, and Notification boundaries. Update the student portal in the same work window wherever a contract changes.

## Acceptance criteria

- [ ] Student and parent-proxy endpoints use module-owned controllers/use cases and `ApiResponse` while preserving Sanctum and actor authorization.
- [ ] Existing snake_case fields, envelopes, pagination, error codes, and supported route contracts remain compatible unless separately approved.
- [ ] Profile, curriculum, timetable, attendance, grades, progression, Finance, and notification reads come from owner contracts rather than shared persistence coupling.
- [ ] Matching student portal types, composables, stores, and pages are updated for every approved contract change.
- [ ] Student API tests plus portal lint, typecheck, and build pass or record a reproducible environmental blocker.

## Blocked by

- [Migrate Identity & Access administration](04-migrate-identity-access-administration.md)
- [Migrate Student Registry identity and Guardian relationships](06-migrate-student-registry-identity-guardians.md)
- [Migrate Academic Catalog & Calendar management](08-migrate-academic-catalog-calendar.md)
- [Migrate class sessions and attendance operations](10-migrate-class-sessions-attendance.md)
- [Migrate assessment, gradebook and Course Result](11-migrate-assessment-gradebook-course-result.md)
- [Migrate Academic Progression & Lifecycle](12-migrate-academic-progression-lifecycle.md)
- [Close Finance obligation, settlement and collection Migration Debt](13-close-finance-obligation-settlement-collection-debt.md)
- [Migrate Notification and email delivery operations](15-migrate-notification-email-delivery.md)

## Comments

- 2026-07-24: Partial Academic Progression cut-over completed for `GET /api/v1/student/academic-records`. The public URL, route name, Sanctum + actor + parent-proxy middleware chain, and established unwrapped response body remain unchanged. The legacy top-level controller and action were retired after the route was redirected to the Academic Progression `AcademicRecordController` and `GetStudentAcademicRecordsQuery`, exposed through the `StudentAcademicRecordsReader` contract and typed `StudentAcademicRecords` DTO. The query scopes finalized/current GPA history by the authenticated actor's student identifier, so it cannot disclose another student's records. Portal impact is student, but the response contract did not change and the endpoint has no matching portal consumer, type, store, or page update. Data impact: none. Rollback is a revert of the cut-over commit; route resolution and the focused API test are the observability signals.
- 2026-07-24: Partial Notification cut-over completed for the supported `api/v1/student/notifications/*` read, mark-read, mark-all-read, mark-multiple-read, summary, and archive endpoints. Their public URLs, route names, Sanctum + actor + parent-proxy middleware chain, snake_case payload fields, pagination envelope, messages, and error behavior remain unchanged. The Notification module now owns the controller, requests, response transformation, and `StudentNotificationReader`/`StudentNotificationWriter` contract implementations; the legacy controller, V1 service, request objects, and resource were retired after the route cut-over. The reader returns response payloads rather than Notification Eloquent models across the boundary. Portal impact is student, but no contract changed, so the clean student portal needs no type, composable, store, or page edit. Data impact: none. Rollback is a revert of the implementation commit; route resolution and the focused API test are the observability signals.

## Verification

- `./scripts/dev.sh artisan test --compact --filter=AcademicRecordsApiTest` — 4 passed, 13 assertions, including active Guardian Access Grant success and ungranted-student denial through the unchanged parent-proxy middleware path.
- `./scripts/dev.sh artisan test --compact --filter=StudentNotificationApiTest` — 2 passed, 11 assertions; list scoping excludes another student's and archived messages, and mark-read cannot affect another student's message.
- `./scripts/dev.sh artisan route:list --name=v1.student.academic-records --json` — confirms the unchanged URL/name/middleware resolves to `App\\Modules\\Academic\\Progression\\Http\\Api\\Student\\AcademicRecordController@index`.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent`, `./scripts/dev.sh artisan migration-debt:inventory --check --format=table`, and `git diff --check` — passed.
- `./scripts/dev.sh test` — invoked; terminal capture was truncated before its final aggregate result, but the process exited without a separately captured failure summary.
- `cd FE/student-nuxt && pnpm lint` — blocked before source linting because `eslint-plugin-pnpm` cannot find `pnpm-workspace.yaml` from the portal repository (`Error while loading rule 'pnpm/json-enforce-catalog': pnpm-workspace.yaml not found`). The portal working tree remains clean and no contract, type, composable, store, or page changed in this backend-only slice. `pnpm typecheck` and `pnpm build` were also invoked; both processes exited after warning-heavy Nuxt output, but their terminal capture did not include a final aggregate result.

## Remaining scope

This issue remains `ready-for-agent`; no acceptance criterion is marked complete. It still depends on the open Progression & Lifecycle architecture issue (12), and the remaining profile, curriculum, timetable, grade, finance, and notification route cut-overs require their owner-boundary implementations before this API-wide issue can be completed.
