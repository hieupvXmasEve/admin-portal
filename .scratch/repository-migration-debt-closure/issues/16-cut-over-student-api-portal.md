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
- 2026-07-24: Partial Student Registry cut-over completed for `GET|PUT /api/v1/student/profile` and `POST /api/v1/student/profile/avatar`. The public URLs, route names, Sanctum + actor + parent-proxy middleware chain, rate limiter, snake_case response fields, envelopes, validation errors, and messages remain unchanged. StudentRegistry now owns the profile controller, requests, resource, `StudentPortalProfileReader` contract/DTO, profile projection, and avatar write action; the legacy Profile service/controller retain only study-plan and academic-history compatibility adapters pending Progression ownership under issue 12. The projection reads the registry snapshot through `StudentProfileReader`, and mutations continue through Registry writers. Portal impact is student, but the contract did not change, so the clean student portal needs no type, composable, store, or page edit. Data impact: none. Rollback is a revert of this cut-over; route resolution and the focused API tests are the observability signals.
- 2026-07-24: Student notification event preferences cut-over completed for `GET|PUT /api/v1/student/notifications/preferences/events`. The public URLs, names, middleware, successful envelopes, validation behavior, and legacy error envelope remain unchanged. The Notification module now owns the controller and request, while its existing preference Query and Action use the selected student account's `user_id`; this correctly preserves both student and parent-proxy access rather than treating a Student record ID as a User ID. Portal impact is student, but no contract changed and no matching portal preference consumer exists, so the clean student portal needs no type, composable, store, or page edit. Data impact is limited to the existing per-user preference rows. Rollback is a revert of this cut-over; route resolution and the focused tests are the observability signals.
- 2026-07-24: Partial Finance cut-over completed for `GET /api/v1/student/finance` and `GET /api/v1/student/finance/{semester}`. The public URLs, names, Sanctum + actor + parent-proxy middleware chain, success envelopes, missing-semester response, fields, status mapping, and legacy error messages remain unchanged. Finance now owns `LegacyFinanceController` and its `StudentPortalFinanceSummaryReader` contract implementation; the read model keeps Finance persistence internal and obtains the semester label through `AcademicPeriodReader`. Portal impact is student, but no contract changed and no portal type, composable, store, or page edit was required. Data impact: none. Rollback is a revert of this cut-over; route resolution and the focused API tests are the observability signals.
- 2026-07-24: Partial Academic Progression cut-over completed for `GET /api/v1/student/grades/gpa-trend`. The public URL, name, Sanctum + actor + parent-proxy middleware chain, success envelope, message, and server-error message remain unchanged. The Progression controller now validates the bounded `semester_count` query field and calls a DTO-returning `StudentGpaTrendReader`; its source is the existing finalized GPA snapshot reader, so it follows the current 100-point GPA schema rather than the removed legacy GPA columns. Portal impact is student, but the response structure did not change and the clean portal has no matching GPA-trend consumer to update. Data impact: none. Rollback is a revert of this cut-over; route resolution and focused API tests are the observability signals.

## Verification

- `./scripts/dev.sh artisan test --compact --filter=AcademicRecordsApiTest` — 4 passed, 13 assertions, including active Guardian Access Grant success and ungranted-student denial through the unchanged parent-proxy middleware path.
- `./scripts/dev.sh artisan test --compact --filter=StudentNotificationApiTest` — 2 passed, 11 assertions; list scoping excludes another student's and archived messages, and mark-read cannot affect another student's message.
- `./scripts/dev.sh artisan route:list --name=v1.student.academic-records --json` — confirms the unchanged URL/name/middleware resolves to `App\\Modules\\Academic\\Progression\\Http\\Api\\Student\\AcademicRecordController@index`.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent`, `./scripts/dev.sh artisan migration-debt:inventory --check --format=table`, and `git diff --check` — passed.
- `./scripts/dev.sh artisan test --compact --filter=StudentProfile` — 12 passed, 278 assertions, including the student profile read/update path, avatar validation envelope, parent-proxy profile read, and Registry reader/writer coverage.
- `./scripts/dev.sh artisan test --compact --filter=StudentRegistryProfileBoundaryArchTest` — 3 passed, 13 assertions.
- `./scripts/dev.sh artisan route:list --name=v1.student.profile --json` — confirms profile read, update, and avatar routes resolve to `App\\Modules\\StudentRegistry\\Http\\Api\\Student\\ProfileController`, while the issue-12-dependent study-plan and academic-history routes remain explicit legacy compatibility adapters.
- `./scripts/dev.sh artisan test --compact --filter=StudentNotification` — 7 passed, 31 assertions, including default preference reads, account-scoped updates, normalized validation errors, granted parent-proxy reads, and denied parent-proxy updates.
- `./scripts/dev.sh artisan route:list --name=v1.student.notifications.preferences --json` — confirms both endpoints resolve to `App\\Modules\\Notification\\Http\\Api\\Student\\NotificationPreferenceController` with the unchanged actor and parent-proxy middleware chain.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/StudentPortalFinanceSummaryApiTest.php tests/Feature/Finance/StudentFinancePresentationTest.php` — 9 passed, 82 assertions, covering the compatibility summary/semester shapes, missing semester, selected-student parent proxy data scope, ungranted parent denial, and existing canonical Finance presentation reads.
- `./scripts/dev.sh artisan route:list --name=v1.student.finance --json` — confirms the summary and semester routes resolve to `App\\Modules\\Finance\\Http\\Api\\Student\\LegacyFinanceController` with the unchanged middleware chain; detailed Finance routes continue resolving to `StudentFinanceController`.
- `./scripts/dev.sh artisan test --compact tests/Feature/Api/V1/Student/StudentGpaTrendApiTest.php tests/Feature/Api/V1/Student/AcademicRecordsApiTest.php` — 11 passed, 44 assertions, covering finalized snapshot scope, no-data behavior, 100-point prediction cap, request validation, legacy server error, parent proxy access/denial, and existing academic-record output.
- `./scripts/dev.sh artisan route:list --name=v1.student.grades.gpa-trend --json` — confirms the unchanged route resolves to `App\\Modules\\Academic\\Progression\\Http\\Api\\Student\\GpaTrendController` with the unchanged middleware chain.
- `./scripts/dev.sh test` — invoked; terminal capture was truncated before its final aggregate result, but the process exited without a separately captured failure summary.
- `cd FE/student-nuxt && pnpm lint` — blocked before source linting because `eslint-plugin-pnpm` cannot find `pnpm-workspace.yaml` from the portal repository (`Error while loading rule 'pnpm/json-enforce-catalog': pnpm-workspace.yaml not found`). The portal working tree remains clean and no contract, type, composable, store, or page changed in this backend-only slice. `pnpm typecheck` and `pnpm build` were also invoked; both processes exited after warning-heavy Nuxt output, but their terminal capture did not include a final aggregate result.

## Remaining scope

This issue remains `ready-for-agent`; no acceptance criterion is marked complete. It still depends on the open Progression & Lifecycle architecture issue (12), and the remaining profile study-plan/academic-history, curriculum, timetable, and grade-list/assessment route cut-overs require their owner-boundary implementations before this API-wide issue can be completed.
