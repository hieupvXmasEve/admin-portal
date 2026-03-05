# System Architecture

Last updated: 2026-03-05  
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
- Notification: `app/Modules/Notification` (V2 domain event + outbox architecture)
    - Actions: `PublishDomainEventAction`, `DispatchOutboxBatchAction`, `PersistIntentAction`, `SendManualNotificationV2Action`, `RetryDeliveryAction`, `RetryOutboxAction`, `HandleOutboxEventAction`
    - Channels: `EmailChannelAdapter`, `RealtimeChannelAdapter` (contracts: `ChannelAdapter`)
    - Models: `NotificationDelivery`, `NotificationEventOutbox`, `NotificationMessage`
    - Queries: `ListMessagesQuery`, `ListOutboxQuery`, `ListDeliveriesQuery`
    - Support: `EventIntentMapper`, `RecipientResolver`, `NotificationAuditLogger`, `NotificationMetrics`, `PolicyResolver`

### 2.3 Shared Layer

- `app/Services/*` (large shared business logic)
- `app/Models/*`
- shared HTTP middleware/controllers/requests/resources under `app/Http/*`

### 2.4 Frontend

- App entry: `resources/js/app.ts`
- SSR entry: `resources/js/ssr.ts`
- pages/components/composables/types under `resources/js/*`
- current list/filter stack is hybrid (`useInertiaFilters`, legacy `useFilters`/`useTableFilters`, and newer `useServerTableQuery` wrapper)
- Reusable filter components (`resources/js/components/filters/`):
    - `FilterPanel.vue` — grid container with configurable columns and clear button
    - `FilterSearchInput.vue` — debounced search input (300ms default)
    - `FilterDateRange.vue` — date range picker (2 grid cells)
    - `FilterSelect.vue` — select dropdown for enum/status filters

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

- admission-deferral action type is excluded from import path.
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
- Decision detail pagination now follows shared server-table keys (`per_page`, `page`) and still accepts legacy aliases (`linked_per_page`, `linked_page`) for compatibility.
- Student action list/history/detail queries eager-load linked decision identity fields.

Frontend list workflow baseline:

- `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue` now uses shared server-table primitives.
- Query/pagination orchestration uses `resources/js/composables/useServerTableQuery.ts`.
- Shared UI primitives are `resources/js/components/filters/ServerDateRangeFilters.vue` and `resources/js/components/tables/ServerPaginatedDataTable.vue`.

## 6) Notification V2 Foundation (Phase 1)

Code baseline (`app/Modules/Notification/*` + `database/migrations/2026_03_03_120*.php`):

- Domain events are published to `notification_event_outbox` (pending/dispatch lifecycle).
- Outbox dispatch pipeline is run by `notifications:process-outbox` (`app/Modules/Notification/Console/ProcessNotificationOutboxCommand.php`) and scheduled every minute in `routes/console.php`.
- Dispatch flow persists `notification_messages` and `notification_deliveries`, then queues per-delivery jobs.

Pipeline flow:

```text
Event -> Intent (EventIntentMapper) -> Policy (PolicyResolver) -> Persist (PersistIntentAction) -> Dispatch (DispatchOutboxBatchAction) -> Track (NotificationDelivery)
```

DB tables:

- `notification_event_outbox` — outbox pattern for domain events
- `notification_messages` — canonical recipient notification records
- `notification_deliveries` — per-channel delivery tracking

Ops monitoring routes (`routes/web/notifications.php`):

- `admin.notifications.ops.outbox` — outbox list with status/date filters
- `admin.notifications.ops.outbox.detail` — single outbox record detail
- `admin.notifications.ops.outbox.retry` — retry failed outbox records
- `admin.notifications.ops.deliveries` — delivery list with channel/status filters
- `admin.notifications.ops.deliveries.retry` — retry failed deliveries
- `admin.notifications.ops.messages` — message list with recipient/status filters

Frontend ops pages (`resources/js/pages/Admin/Notifications/Ops/`):

- `Outbox.vue`, `OutboxDetail.vue`, `Deliveries.vue`, `Messages.vue`

Phase 1 guardrails:

- Strict campus isolation: recipient resolution rejects cross-campus targets (`RecipientResolver` checks campus for User/Student/Lecture targets).
- Canonical recipient identity is `recipient_user_id` in `notification_messages`; non-user targets are resolved to user ids before message/delivery persist.
- No legacy backfill in Phase 1: new tables are clean-slate and legacy `notifications` history is not migrated.

## 7) Runtime Scheduled Commands

Scheduled via `routes/console.php` with `onOneServer()` guard:

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `notifications:process-outbox --limit=100` | Every minute | Process pending outbox events |
| `sessions:update-statuses` | Every 30 min | Update class session statuses |
| `events:process-completions` | Hourly | Process event completions |
| `events:send-reminders` | Daily 00:10 | Send event reminders |
| `events:process-failed-gold-rewards` | Daily 02:00 | Retry failed gold rewards |
| `academic-records:sync` | Daily 03:00 | Sync from Canvas |
| `attendance:sync-to-academic-records` | Every 2 hours | Sync attendance |
| `academic-records:aggregate-manual` | Daily 04:00 | Aggregate manual grades |

### Queue Worker Requirements

Notification V2 requires active queue worker for delivery jobs:

```bash
php artisan queue:work --sleep=1 --tries=3
```

Queue jobs:

- `ProcessNotificationOutboxJob` — Process individual outbox events
- `SendNotificationDeliveryJob` — Deliver via email/realtime channels

Production setup: See `docs/deployment-guide.md` for supervisor configuration.

## 9) Operational Architecture Status

- Docker assets are maintained in `docker/`.
- Scripts in `scripts/` still contain path and runtime drift:
    - multiple scripts reference root compose names (for example `docker-compose.production.yml`) while actual files live under `docker/`
    - setup/validation flows reference missing helpers (`scripts/docker-compose-dev.sh`, `scripts/test-local.sh`)
    - deployment scripts are duplicated with overlapping intent (`scripts/prod.sh`, `scripts/deploy.sh`, `scripts/deploy-production.sh`)
- CI workflow YAML files exist but are disabled.

## 10) Architecture Risks

- Hybrid layering (module-domain folders plus a large shared service layer) creates ownership ambiguity.
- API auth model is not fully uniform across all route groups.
- Public config endpoints create configuration exposure/tampering risk.
- CI disabled + script drift increases deployment and regression risk.
- Notification read history is split between legacy and V2 data until later cutover/backfill phases.

## 11) Near-Term Decisions Required

1. Standardize API auth model for finance and other mixed groups.
2. Resolve public `system-config` exposure strategy.
3. Select canonical deployment workflow and align scripts.
4. Reactivate CI with minimum required gates.
5. Define Notification V2 cutover/backfill plan beyond Phase 1 clean-slate tables.

## Unresolved Questions

- Which API groups are official external contracts vs internal web-support endpoints?
- Should finance APIs migrate to actor-based Sanctum protection?
- Should campus-sensitive permission checks rely less on session-only context for API calls?
