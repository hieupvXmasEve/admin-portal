# System Architecture

Last updated: 2026-06-24
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
- Finance: `app/Modules/Finance` (Settlement v2 landed 2026-03-25; DNG gateway integration 2026-03-26; EGC fee-management ops expanded 2026-04-21)
    - Source-of-truth model:
        - `payments` — canonical cash receipt ledger
        - `payment_applications` — line-level cash application (replaces legacy `payment_allocations`)
        - `invoice_lines` — settlement anchor with status/void lifecycle (active|void, voided_at, void_reason)
        - `invoice_discounts` — discount headers
        - `discount_allocations` — line-level discount allocation ledger
        - `student_invoices` snapshot columns (`subtotal`, `discount_total`, `total_amount`, `paid_amount`) as cache only
        - `dng_payment_requests` — DNG gateway payment request audit
        - `dng_webhook_events` — DNG webhook event audit for payment confirmation
    - Services: `SettlementService`, `PaymentService`, `FinanceChargeService`, `DngClient`, `DngPaymentService`, `DngReconciliationService`, `DngWebhookService`, `DngChecksumService`
    - Actions: `VoidFinanceChargeAction`, `AllocatePaymentAction`, `AutoAllocatePaymentsAction`
    - EGC tracking/actions:
        - `SyncEgcBlockResultsAction`
        - `ApplyEgcRetakeDiscountAction`
        - `ApplyEgcCarryForwardAction`
        - `BuildEgcCarryForwardPlanAction`
        - `GenerateEgcChargesAction`
    - EGC query/read-model layer:
        - `ListEgcBlockResultsQuery`
        - `ListEgcRetakeAdjustmentsQuery`
        - `ListEgcCarryForwardCandidatesQuery`
        - `PreviewEgcChargeGenerationQuery`
    - Support: `StudentChargeTimingResolver` (EGC vs Tuition billing by semester & stage; EGC until `intake_major`)
    - Web routes: `/finance/payments/create`, `/finance/payments/{student}/dng-data`, `/finance/dng/payment-requests`, `/finance/dng/payment-requests/{dngPaymentRequest}`, `/finance/dng/webhook-events`, `/finance/dng/webhook-events/{dngWebhookEvent}`
    - API routes: `POST /api/v1/finance/dng/payment-requests` (create), `POST /api/v1/finance/dng/webhook` (receive)
    - Ops routes: `finance/operations/settlement` (worklist), `finance/operations/dashboard` (metrics)
    - EGC ops routes:
        - `finance/egc/block-results`
        - `finance/egc/retake-adjustments`
        - `finance/egc/carry-forward`
        - `finance/egc/generate-charges`
    - EGC campus scope:
        - current ops list pages are locked to `session('current_campus_id')`
        - no campus picker is exposed on the current EGC pages
    - EGC data truth:
        - `egc_blocks` tracks per-semester block order, level, result, retake flag, and mapped `finance_charge_id`
        - `egc_retake_discount_links` binds failed source block -> target charge -> discount header
        - carry-forward releases unused paid EGC charges back to unapplied balance by voiding unused charges without immediate auto-allocation
        - retake discounts only target later mapped retake blocks of the same level
        - `egc:backfill-blocks` rebuilds historical EGC blocks from registrations, repairs null `finance_charge_id`, and reports unmatched charges instead of inventing new blocks
    - DNG workflow: Staff → Payment Create form → Student selection → resolve `campuses.dng_code` → DNG API push (with checksum) → QR display → Webhook inbox capture (`dng_webhook_events`) → Async checksum/business validation → Payment record
    - DNG replacement rule: for the same `student + fee_type`, only one unpaid DNG request should stay active; older unpaid requests move to `cancelled` after the replacement push succeeds
    - Late webhook/reconciliation events for cancelled requests are skipped
    - Admin monitoring workflow: request audit list/detail remain campus-scoped; webhook audit list is cross-campus while webhook detail still validates campus access
    - Permissions: `create_finance_payments`, `view_finance_dng_payment_requests`, `view_finance_dng_webhook_events`
    - Legacy `payment_allocations` no longer used in runtime.
- Notification: `app/Modules/Notification` (V2 domain event + outbox architecture)
    - Actions: `PublishDomainEventAction`, `DispatchOutboxBatchAction`, `PersistIntentAction`, `SendManualNotificationV2Action`, `RetryDeliveryAction`, `RetryOutboxAction`, `HandleOutboxEventAction`
    - Channels: `EmailChannelAdapter`, `RealtimeChannelAdapter` (contracts: `ChannelAdapter`)
    - Models: `NotificationDelivery`, `NotificationEventOutbox`, `NotificationMessage`
    - Queries: `ListMessagesQuery`, `ListOutboxQuery`, `ListDeliveriesQuery`
    - Support: `EventIntentMapper`, `RecipientResolver`, `NotificationAuditLogger`, `NotificationMetrics`, `PolicyResolver`
    - Email SMTP resolution: `EmailConfiguration` table is campus-scoped; `getActiveForCampus(?int)` resolves campus-specific config (priority) or falls back to global; `SendSingleEmailJob` threads `campus_id` through the dispatch chain

### 2.3 Academic Progression and Status Logging

Current academic progression baseline:

- `academic_progression_events` is the semantic audit/event store for academic placement and progression.
- Event types:
    - `ENGLISH_LEVEL_CHANGED` — EGC level changes (manual & auto progression after course completion)
    - `COURSE_STAGE_CHANGED` — stage transitions (e.g., `intake_pre_uni_gc` → `intake_course`)
- Stage change events trigger `PublishCourseStageChangedNotificationAction` → V2 outbox publication.
- `student_changes` may still exist for generic field-level audit, but it is not the source of truth for academic level/stage history.

Current EGC to major baseline:

- Student major transition writes `students.status = intake_course`.
- Transition semester for tuition is stored in `students.intake_major` (active).
- Deprecated: `students.gc_to_course_transition_semester` (no longer used in settlement v2).
- Course completion validation uses `MarkCourseOfferingCompletedAction` with attendance + EGC progression rules.

### 2.4 Shared Layer

- `app/Services/*` (large shared business logic)
- `app/Models/*`
- shared HTTP middleware/controllers/requests/resources under `app/Http/*`

### 2.5 Frontend

- App entry: `resources/js/app.ts`
- SSR entry: `resources/js/ssr.ts`
- pages/components/composables/types under `resources/js/*`
- current list/filter stack is hybrid (`useInertiaFilters`, legacy `useFilters`/`useTableFilters`, and newer `useServerTableQuery` wrapper)
- Reusable filter components (`resources/js/components/filters/`):
    - `FilterPanel.vue` — grid container with configurable columns and clear button
    - `FilterSearchInput.vue` — debounced search input (300ms default)
    - `FilterDateRange.vue` — date range picker (2 grid cells)
    - `FilterSelect.vue` — select dropdown for enum/status filters
- Finance operations frontend now has two finance-specific ops views aligned to the new settlement model:
    - `resources/js/pages/Finance/Operations/Settlement.vue`
    - `resources/js/pages/Finance/Operations/Dashboard.vue` (cards/tables renamed toward gross/discounts/cash applied/outstanding semantics)

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
- `POST /reports/student-decisions/{studentDecision}/students/preview`
- `POST /reports/student-decisions/{studentDecision}/students/bulk-link`
- `POST /reports/student-decisions/{studentDecision}/students/{actionLog}/unlink`
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
- Decision detail bulk add uses `PreviewStudentDecisionBulkLinkQuery` to normalize pasted student codes, require a selected `action_type`, enforce current-campus matching, show unmatched/skipped rows, and list only unlinked action logs of that selected type as linkable.
- Decision detail bulk confirm uses `BulkLinkStudentsToDecisionAction` to update eligible same-`action_type` `student_action_logs.decision_id` rows inside a transaction without creating new action logs or overwriting action logs already linked to another decision.
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
    - Current implementation baseline for new student-facing academic notifications is V2-only. Course completion, EGC completion/progression, EGC program completion, and course-stage transition notifications publish domain events and persist through the Notification V2 outbox pipeline.
- AI: `app/Modules/AI` (provider settings, audit/evaluation foundation, metric catalog/query-plan validation, query metrics and entity search tool execution, staff copilot chat/SSE runtime)
    - Provider settings: `ai_provider_settings` stores one encrypted staff-owned provider setting per user with provider/model allowlists, key masking, cost limits, and provider test metadata.
    - Audit/evaluation/runtime foundation: `ai_conversations`, `ai_messages`, `ai_agent_traces`, `ai_tool_calls`, `ai_provider_usages`, `ai_feedback`, `ai_evaluation_cases`, `ai_evaluation_runs`, `ai_evaluation_results`, `ai_chat_runs`, and `ai_run_events`.
    - Metric catalog/query-plan foundation: code-defined aggregate MetricCatalog v1, business glossary subset, query-plan DTO/validator, `view_ai_metrics` permission, and deterministic staff metric evaluation cases.
    - Query metrics tool MVP: internal `ToolRegistry`/`ToolDispatcher`, read-only `query_metrics` execution, bounded aggregate `QueryMetricsResult`, and MetricCatalog v1 resolvers for Academic status/defer, Finance collection, Fee Monitor, and DNG lifecycle metrics.
    - Entity catalog/search MVP: code-defined EntityCatalog v1, `search_entities:v1`, `SearchEntitiesTool`, bounded `EntitySearchResult`, scoped opaque entity references, and first accepted entity types `student`, `program`, `semester`, and `course_offering` with `class` as the user-facing course-offering alias.
    - Staff copilot: `/ai/copilot` renders `resources/js/pages/AI/StaffCopilot/Index.vue`; `/ai/copilot/messages` accepts a bounded question only and queues a durable assistant run; `/ai/copilot/runs/{run}/events` replays normalized Laravel SSE events and executes the live/deterministic runner; `/ai/copilot/runs/{run}/cancel` cancels queued active runs with owner/campus checks; `/ai/copilot/runs/{run}/retry` retries failed runs without duplicating the original user message. Completed runs still record conversation/user-message/trace/tool-call/provider-usage/assistant-message evidence and return source-cited structured answer props from audited `query_metrics` or explicit `search_entities` results.
    - AI cross-module metric/entity reads use shared contracts (`App\Shared\Contracts\Academic\AiAcademicMetricReader`, `App\Shared\Contracts\Academic\AiAcademicEntitySearchReader`, `App\Shared\Contracts\Finance\AiFinanceMetricReader`) with owning-module adapters; AI does not import Academic/Finance query classes directly.
    - Support services: `AiRedactor`, `AiAuditRecorder`, `AiProviderUsageRecorder`, `AiEvaluationRunner`, `BusinessGlossary`, `MetricCatalog`, `EntityCatalog`, `QueryPlanValidator`, `ToolRegistry`, `ToolDispatcher`, `QueryMetricsTool`, `SearchEntitiesTool`, `StaffCopilotSseRuntime`, `LiveStaffCopilotAgent`, and fallback-aware `StaffCopilotAgentRunner`.
    - Current boundary: internal staff chat can use provider-agnostic downstream SSE and a staff-owned live provider setting for structured planning/final synthesis, but data access remains read-only through MetricCatalog v1, EntityCatalog v1, `query_metrics`, and `search_entities`; no WebSocket/Reverb/Pusher dependency, MCP exposure, write/action mode, profile-section reads, or student/lecturer portal behavior.

## 7) Runtime Scheduled Commands

Scheduled via `routes/console.php` with `onOneServer()` guard:

| Command                                    | Frequency     | Purpose                       |
| ------------------------------------------ | ------------- | ----------------------------- |
| `notifications:process-outbox --limit=100` | Every minute  | Process pending outbox events |
| `sessions:update-statuses`                 | Every 30 min  | Update class session statuses |
| `events:process-completions`               | Hourly        | Process event completions     |
| `events:send-reminders`                    | Daily 00:10   | Send event reminders          |
| `events:process-failed-gold-rewards`       | Daily 02:00   | Retry failed gold rewards     |
| `academic-records:sync`                    | Daily 03:00   | Sync from Canvas              |
| `attendance:sync-to-academic-records`      | Every 2 hours | Sync attendance               |
| `academic-records:aggregate-manual`        | Daily 04:00   | Aggregate manual grades       |

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
- Finance dashboard and some finance read models still carry transitional risk while snapshot columns are gradually reconciled and old data is backfilled into `discount_allocations`.

## 11) Near-Term Decisions Required

1. Standardize API auth model for finance and other mixed groups.
2. Resolve public `system-config` exposure strategy.
3. Select canonical deployment workflow and align scripts.
4. Reactivate CI with minimum required gates.
5. Define Notification V2 cutover/backfill plan beyond Phase 1 clean-slate tables.
6. Finish finance settlement cutover for all remaining read models and legacy finance reports.

## Unresolved Questions

- Which API groups are official external contracts vs internal web-support endpoints?
- Should finance APIs migrate to actor-based Sanctum protection?
- Should campus-sensitive permission checks rely less on session-only context for API calls?
