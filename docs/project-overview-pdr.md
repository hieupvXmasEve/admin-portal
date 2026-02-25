# Project Overview and PDR

Last updated: 2026-02-23
Scope baseline: repository state in `main` workspace

## 1) Product Summary

Swinx is a multi-role education operations system with:
- Admin/staff web workflows (Laravel + Inertia)
- Student API workflows (`/api/v1/student/*`)
- Lecturer API workflows (`/api/v1/lecturer/*`)
- Domain modules for Identity, Academic, and Finance

Primary objectives:
- Manage student lifecycle and academic records
- Support finance operations (charges, invoices, payments)
- Provide role-based, campus-aware access
- Expose API surfaces for portal/mobile integrations

## 2) Users and Personas

- Admin/Operations staff: manage master data, student records, finance operations, reporting
- Lecturers: course, attendance, assessment, timetable workflows via API
- Students: profile, timetable, grades, finance, clubs/events/forms via API
- Parents: indirect student-context access where allowed via `parent.student.access`

## 3) In-Scope Functional Requirements (Initial Baseline)

### FR-01 Identity and Authentication

- Web authentication and campus selection are handled through Identity module routes.
- Student auth endpoints exist under module routes and API v1 routes.
- Lecturer auth endpoints exist under module routes and API v1 routes.
- Sanctum-based API auth is required for protected API groups.

Acceptance criteria:
- Web login and logout routes resolve.
- Student and lecturer login/logout/refresh routes resolve.
- Protected API groups enforce `auth:sanctum`.

### FR-02 Campus Context and Authorization

- Web requests append `CheckCampusSelected` and `SetCampus` middleware.
- Permission checks exist in many admin web routes via `can:*` middleware.
- Student and lecturer API middleware enforce actor-specific authorization.

Acceptance criteria:
- Authenticated web users without campus context are redirected to campus selection.
- Admin pages and APIs require relevant permissions where configured.
- Student/lecturer API requests fail with authorization errors when role mismatch occurs.

### FR-03 Academic Domain Workflows

- Student management and academic summaries are present in web routes and Academic module.
- Student API includes dashboard, timetable, grades, attendance, curriculum, calendar, and forms.
- Lecturer API includes dashboard, courses, attendance, timetable, sessions, students, and assessments.

Acceptance criteria:
- Route groups for these areas resolve from `routes/web.php` + module routes + API v1 routes.
- Controllers exist for key student/lecturer API endpoints.

### FR-04 Finance Domain Workflows

- Finance module provides admin web routes for operations dashboard, charges, invoices, and payments.
- Student finance endpoints exist in API v1 student routes and module finance routes.

Acceptance criteria:
- Finance web and API route groups resolve.
- Finance actions/queries/services exist under `app/Modules/Finance/` and `app/Services/`.

### FR-05 Notifications and Realtime

- Broadcasting routes are configured with Sanctum middleware for API auth context.
- Frontend has Echo setup (`resources/js/lib/echo.ts`) and realtime composables.

Acceptance criteria:
- Broadcast channels are defined.
- Frontend can initialize Echo when env keys are provided.

### FR-06 Operational Tooling

- Deployment, backup, monitoring, and environment scripts exist under `scripts/`.
- Docker assets exist under `docker/` for dev/local-prod/prod variants.

Acceptance criteria:
- Scripts and compose assets are documented with current known limitations.

## 4) Non-Functional Requirements

- Security:
  - Sanctum auth for API endpoints
  - middleware-driven authorization
  - centralized API error envelope via `App\Http\Responses\ApiResponse`
- Maintainability:
  - domain modules under `app/Modules/*`
  - frontend feature grouping under `resources/js/pages/*`
- Performance:
  - Laravel caching/optimization commands available
  - queue and scheduler hooks present in `routes/console.php`
- Observability:
  - logging middleware and monitoring scripts exist

## 5) Technical Constraints and Dependencies

- PHP: Composer currently requires `^8.2`
- Framework: Laravel `^12.0`
- Frontend runtime: Node/npm + Vite
- DB/cache dependencies: MySQL/MariaDB + Redis
- API consumers must handle mixed auth context patterns (`web` middleware in some admin APIs vs Sanctum in external APIs)

## 6) Risks and Current Gaps

- Some student/lecturer API endpoints are intentionally TODO/commented.
- Rate-limit middleware is partially commented in API v1 route files.
- Environment and deployment scripts contain path assumptions that are currently inconsistent with repository layout.
- CI workflow definitions exist but are disabled (commented out).
- Test coverage is minimal relative to application size.

## 7) Success Metrics for Baseline Phase

- Baseline docs exist for overview, architecture, standards, roadmap, deployment, and design.
- README is aligned with verified repository state.
- Known gaps are explicitly documented for stabilization planning.

## 8) Version History

- 2026-02-23: Initial baseline PDR created from repository audit + repomix snapshot.

## Unresolved Questions

- Which PHP version should be treated as canonical for this repo: `8.2+` (Composer) or `8.4+` (some existing docs/rules)?
- Which Docker workflow is intended as canonical: direct `docker compose -f docker/...` usage or script wrappers in `scripts/`?
- Should admin internal APIs keep `web` middleware dependency, or migrate to fully token-based access?
- Which TODO endpoints in student/lecturer APIs are planned for near-term completion?
