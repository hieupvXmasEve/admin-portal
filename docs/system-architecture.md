# System Architecture

Last updated: 2026-02-27  
Owner: Platform Team  
Status: Current-state architecture map  
Source of truth: route files, middleware, module providers, runtime entrypoints

## 1) Architecture Overview

Swinx is a Laravel 12 monolith with Vue 3 + Inertia frontend and mixed web/API surfaces.

High-level flow:

```text
Client (Web SPA / API)
  -> Routes + Middleware (web/api groups)
    -> Controllers
      -> Module Actions/Queries or Shared Services
        -> Eloquent Models
          -> MySQL/MariaDB
        -> Redis (cache/queue)
```

## 2) Core Layers

### 2.1 Entry and Routing

- Bootstrap: `bootstrap/app.php`
- Web routes: `routes/web.php` + `routes/web/*`
- API root: `routes/api.php`
- Student API v1: `routes/api/v1/student.php`
- Lecturer API v1: `routes/api/v1/lecturer.php`

### 2.2 Domain Modules

- Identity: `app/Modules/Identity`
- Academic: `app/Modules/Academic`
- Finance: `app/Modules/Finance`

### 2.3 Shared Layer

- `app/Services/*` (large shared business logic)
- `app/Models/*`
- shared HTTP middleware/controllers/requests/resources under `app/Http/*`

### 2.4 Frontend

- App entry: `resources/js/app.ts`
- SSR entry: `resources/js/ssr.ts`
- pages/components/composables/types under `resources/js/*`

## 3) Auth and Middleware Surface Map

### Student API surface

- Main middleware chain: `auth:sanctum`, `api.logging`, `api.actor:student_or_parent`
- Additional branch: `either:parent.student.access,student.api.auth`

### Lecturer API surface

- Main middleware chain: `auth:sanctum`, `api.actor:lecturer`, `lecturer.api.auth`, `api.logging`

### Parent auth/context surface

- Routes under `/api/v1/student/parent/*`
- Protected chain: `auth:sanctum`, `api.logging`, `api.actor:parent`

### Known mixed-auth exceptions

- `routes/api.php` exposes `/api/system-config*` without auth middleware.
- Finance module API routes (`app/Modules/Finance/routes/api.php`) use `web` + `auth` middleware.

## 4) Identity Token Lifecycle (Current)

- Login/refresh actions issue 8-hour tokens.
- Student, lecturer, and parent refresh endpoints are protected routes.
- Refresh controller pattern is issue new token then revoke current token.

## 5) Student Action Import Sub-Architecture

Route cluster (`app/Modules/Academic/routes/web.php`):

- import page
- template download
- preview import
- execute import

Flow:

1. Preview parses and validates uploaded rows.
2. Preview returns token and stores anti-tamper context (hash + optional attachment id).
3. Execute verifies preview token and anti-tamper data.
4. Execute writes row-by-row with DB transaction boundaries.

Constraints:

- `ADMISSION_DEFERRAL` excluded from import path.
- append is limited to same student + same type + same period.

### Student Decisions Registry and Link Model

Route cluster (`app/Modules/Academic/routes/web.php`):

- `GET /reports/student-decisions`
- `GET /reports/student-decisions/{studentDecision}`
- `POST /reports/student-decisions`
- `PUT /reports/student-decisions/{studentDecision}`

Data and relation baseline:

- Registry table: `student_decisions`
- Link column: `student_action_logs.decision_id` (nullable FK to `student_decisions.id`, `nullOnDelete`)
- Model relations:
    - `StudentDecision::actionLogs()`
    - `StudentActionLog::decision()`

Query behavior baseline:

- Decision listing computes `linked_actions_count` and `linked_students_count`.
- Decision index contract supports `search`, `issued_from`, `issued_to`, `per_page`, `page`, `sort`, and `direction`.
- Decision index sort columns are allowlisted to `decision_number`, `decision_signer`, `issued_at`, and `expires_at` with sanitized defaults (`issued_at` + `desc`).
- Decision detail view paginates linked student action logs.
- Student action list/history/detail queries eager-load linked decision identity fields.

Frontend list workflow baseline:

- `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue` now uses shared server-table primitives.
- Query/pagination orchestration uses `resources/js/composables/useServerTableQuery.ts`.
- Shared UI primitives are `resources/js/components/filters/ServerDateRangeFilters.vue` and `resources/js/components/tables/ServerPaginatedDataTable.vue`.

## 6) Operational Architecture Status

- Docker assets are maintained in `docker/`.
- Scripts in `scripts/` still contain path and runtime drift.
- CI workflow YAML files exist but are disabled.

## 7) Architecture Risks

- Hybrid layering (`Modules` + large shared `Services`) creates ownership ambiguity.
- API auth model is not fully uniform across all route groups.
- Public config endpoints create configuration exposure/tampering risk.
- CI disabled + script drift increases deployment and regression risk.

## 8) Near-Term Decisions Required

1. Standardize API auth model for finance and other mixed groups.
2. Resolve public `system-config` exposure strategy.
3. Select canonical deployment workflow and align scripts.
4. Reactivate CI with minimum required gates.

## Unresolved Questions

- Which API groups are official external contracts vs internal web-support endpoints?
- Should finance APIs migrate to actor-based Sanctum protection?
- Should campus-sensitive permission checks rely less on session-only context for API calls?
