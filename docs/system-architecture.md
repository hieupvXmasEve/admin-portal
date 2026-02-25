# System Architecture

Last updated: 2026-02-23
Architecture type: Laravel monolith + Inertia SPA + API surfaces

## 1) Architecture Overview

Swinx is a modular Laravel application with two primary delivery modes:
- Server-rendered web app via Inertia (admin/staff workflows)
- JSON APIs for student/lecturer flows

High-level runtime:

```text
Browser / Mobile Client
  -> Laravel HTTP Layer (routes + middleware)
    -> Domain Logic (Modules + Services + Actions/Queries)
      -> MySQL/MariaDB
      -> Redis
  -> Optional Realtime (Broadcast channels / Echo)
```

## 2) Major Components

### 2.1 Presentation Layer

- Web entry points: `routes/web.php` + route files in `routes/web/`
- Inertia middleware + shared props: `app/Http/Middleware/HandleInertiaRequests.php`
- Frontend runtime:
  - `resources/js/app.ts` (SPA boot)
  - `resources/js/ssr.ts` (SSR boot)
  - `resources/js/layouts/*`, `resources/js/components/*`, `resources/js/pages/*`

### 2.2 API Layer

- API root: `routes/api.php`
- Student APIs: `routes/api/v1/student.php`
- Lecturer APIs: `routes/api/v1/lecturer.php`
- Admin/internal APIs: `routes/api/admin.php`, `routes/api/modules.php`

### 2.3 Domain Layer

- `app/Modules/Identity`: authentication flows, campus selection, context endpoints
- `app/Modules/Academic`: student academic/placement/action workflows
- `app/Modules/Finance`: billing, charges, payments, finance operations

### 2.4 Shared Application Layer

- `app/Services/*`: broad service layer with many high-complexity classes
- `app/Models/*`: Eloquent models and relationships
- `app/Http/Requests/*`: validation
- `app/Http/Resources/*`: resource transforms

### 2.5 Infrastructure Layer

- Database connections configured in `config/database.php`
- Queue configured in `config/queue.php`
- Broadcasting configured in `config/broadcasting.php`
- Docker assets in `docker/` (dev/local-prod/prod variants)

## 3) Request Flow Patterns

### 3.1 Web Flow (Admin/Staff)

1. Request enters web routes.
2. Web middleware stack applies, including campus context middleware.
3. Controller resolves permissions and builds Inertia response.
4. Frontend page receives shared props (`auth`, permissions, campus context).

### 3.2 Student API Flow

1. Request enters `api/v1/student` route group.
2. `auth:sanctum` + API auth middleware run.
3. `either:parent.student.access,student.api.auth` may gate access.
4. Controller executes domain logic and returns JSON.

### 3.3 Lecturer API Flow

1. Request enters `api/v1/lecturer` route group.
2. `auth:sanctum` + `lecturer.api.auth` + logging middleware run.
3. Controller executes lecturer domain logic and returns JSON.

## 4) Authorization Model

- Permission checks are widespread via route-level `can:*` middleware in web/admin flows.
- Campus context for web flows is derived from session (`current_campus_id`).
- API actor restrictions are middleware-driven (`student.api.auth`, `lecturer.api.auth`).

Architectural implication:
- Some admin API endpoints rely on `web` middleware and session context, which may block pure token clients unless adapted.

## 5) Data and Background Work

- MySQL/MariaDB stores transactional domain data.
- Redis is configured for caching/queue backends.
- Scheduler tasks are defined in `routes/console.php` (attendance, events, academic sync jobs).
- Queue worker usage is expected for asynchronous workloads (mail/notifications/jobs).

## 6) Realtime and Notifications

- Broadcast channels in `routes/channels.php`.
- Broadcast auth routes enabled in API routing context.
- Frontend Echo setup in `resources/js/lib/echo.ts`.

## 7) Operational Architecture Notes

- CI workflow definitions are present but currently disabled (commented).
- Deployment and environment scripts exist but include path/reference drift that should be stabilized before relying on them.

## 8) Current Architecture Risks

- Mixed architectural styles (module actions/queries + service-heavy legacy layer) increase maintenance complexity.
- Session-dependent campus context in some API routes complicates external integrations.
- Partial TODO/commented API routes create contract uncertainty for client teams.
- Minimal test coverage increases regression risk for high-complexity domain logic.

## 9) Target Near-Term Architecture Direction

- Keep modules as primary domain boundary for new business logic.
- Normalize API contracts and middleware expectations by consumer type.
- Stabilize deployment workflow and CI activation.
- Increase automated coverage around finance and academic critical paths.

## Unresolved Questions

- Should admin API routes move from `web` middleware to strict token-based middleware for consistency?
- What is the intended long-term split between `app/Modules/*` and `app/Services/*`?
- Is SSR required in production by default, or optional per environment?
