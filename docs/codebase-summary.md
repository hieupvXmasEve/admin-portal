# Codebase Summary

Last updated: 2026-04-21
Owner: Platform Team
Status: Current-state snapshot
Primary source: `repomix-output.xml` (generated 2026-03-02 / updated for runtime code through commit 8651e0f4)

## 1) Snapshot Method

Generated with:

```bash
repomix -o repomix-output.xml
```

Latest repomix summary:

- Total files packed: `2,026`
- Total tokens: `3,151,945`
- Output file: `repomix-output.xml`

Top token-heavy files include large artifacts (`release-manifest.json`, static html, large data JSON), so architectural interpretation should prioritize app/runtime code paths.

## 2) Repository Shape (File Counts)

Current counts:

- `app`: 833
- `resources`: 745
- `database`: 235
- `docs`: 102
- `routes`: 49
- `config`: 21
- `scripts`: 12

## 3) Runtime Architecture Baseline

- Laravel 12 monolith with hybrid layering:
    - module domains in `app/Modules/*`
    - large shared layer in `app/Services/*`, `app/Models/*`, and shared HTTP layers
- Vue 3 + Inertia frontend in `resources/js/*`
- Academic admin now includes a Student Decisions registry with nullable linkage from `student_action_logs.decision_id` to `student_decisions.id`.
- Student action reporting stores EGC defer source block as `student_action_logs.egc_defer_from_block_number`, filtering reports by `from_semester_id` and Block 1/2 without deriving from finance EGC block records.
- Notification V2 module (`app/Modules/Notification/`) implements domain event + outbox pattern with Phase 1 foundation complete:
    - Outbox tables: `notification_event_outbox`, `notification_messages`, `notification_deliveries`
    - Event types in use: `academic.course_stage_changed`, `course_completed`
    - Full audit trail + retry via outbox processor (`notifications:process-outbox` scheduled every minute)
    - Actions: `PublishDomainEventAction`, `DispatchOutboxBatchAction`, `SendManualNotificationV2Action`, retry actions
    - Channels: `EmailChannelAdapter`, `RealtimeChannelAdapter`
    - Email configuration: `email_configurations` table is campus-scoped (`nullable campus_id`); email dispatch resolves campus-specific SMTP config with fallback to global config
- AI module (`app/Modules/AI/`) now has provider settings and backend-only audit/evaluation foundations:
    - Provider settings table: `ai_provider_settings`
    - Audit/evaluation tables: `ai_conversations`, `ai_messages`, `ai_agent_traces`, `ai_tool_calls`, `ai_provider_usages`, `ai_feedback`, `ai_evaluation_cases`, `ai_evaluation_runs`, `ai_evaluation_results`
    - Support services: `AiRedactor`, `AiAuditRecorder`, `AiProviderUsageRecorder`, `AiEvaluationRunner`
    - Runtime boundary: no chat UI, metric tools, MCP exposure, write/action mode, or student/lecturer portal behavior yet.
- Academic progression baseline uses `academic_progression_events` as semantic history:
    - `ENGLISH_LEVEL_CHANGED` for EGC level changes (manual & auto progression)
    - `COURSE_STAGE_CHANGED` for stage transitions (e.g., `intake_pre_uni_gc` → `intake_course`)
- EGC to major billing: `students.intake_major` stores transition semester (active).
- Deprecated: `students.gc_to_course_transition_semester` (no longer used in settlement v2).
- Current new student-facing academic notifications are expected to flow through Notification V2 only.
- Voucher admin surface simplified: Create, Edit, Show (with inline apply card), Apply only. No import/delete flows (2026-03-20).
- Voucher applications write canonical `voucher_applications` table; discount flows through `invoice_discounts` + `discount_allocations`.
- Finance settlement v2 (2026-03-25) now uses invoice-line truth:
    - Cash application: `payment_applications` (replaces legacy `payment_allocations`)
    - Discount headers: `invoice_discounts`
    - Line-level discounts: `discount_allocations` (new table, replaces header-only model)
    - InvoiceLine lifecycle: `status` (active|void), `voided_at`, `void_reason`
    - New services: `SettlementService`, updated `PaymentService`, `FinanceChargeService`
    - New actions: `VoidFinanceChargeAction`, `AllocatePaymentAction`, `AutoAllocatePaymentsAction`
    - New support: `StudentChargeTimingResolver` — EGC vs Tuition billing by semester & stage (EGC until `intake_major` transition)
- EGC fee-management runtime now adds dedicated tracking + ops flows:
    - Tables/models: `egc_blocks`, `egc_retake_discount_links`
    - Actions: `SyncEgcBlockResultsAction`, `ApplyEgcRetakeDiscountAction`, `ApplyEgcCarryForwardAction`, `BuildEgcCarryForwardPlanAction`, `GenerateEgcChargesAction`
    - Queries: `ListEgcBlockResultsQuery`, `ListEgcRetakeAdjustmentsQuery`, `ListEgcCarryForwardCandidatesQuery`, `PreviewEgcChargeGenerationQuery`
    - Web pages:
        - `/finance/egc/block-results`
        - `/finance/egc/retake-adjustments`
        - `/finance/egc/carry-forward`
        - `/finance/egc/generate-charges`
    - Campus scope: current EGC ops pages now read only students in `session('current_campus_id')`
    - Carry-forward truth: `egc_blocks.finance_charge_id` + block `result`
        - mapped block with `result != pending` => consumed
        - unused active paid EGC charges can be released to unapplied balance without auto-reallocation
    - Retake truth: target charge must belong to a later mapped retake block for the same student + level
    - Backfill command behavior:
        - `egc:backfill-blocks` now includes historical/deferred/dropout students with EGC registrations or active EGC charges
        - missing `finance_charge_id` on existing blocks is repaired in place
        - unmatched active EGC charges are reported, not converted into new blocks
- Finance operations include student-centric Settlement Worklist at `finance/operations/settlement`; legacy `payments/auto-allocate` UI redirects there.
- Finance generation/migration flows include backfill commands for voucher and scholarship headers + `discount_allocations`. Zero-amount tuition terms no longer create invoices.
- DNG payment gateway integration (2026-03-26, updated 2026-04-16):
    - Tables: `dng_payment_requests` (audit), `dng_webhook_events` (webhook audit)
    - Controllers: `DngPaymentController` (API), `DngWebhookController` (webhook receiver)
    - Admin controllers: `DngPaymentRequestController`, `DngWebhookEventController`
    - Services: `DngClient` (API comms with checksum), `DngPaymentService` (payment request logic), `DngReconciliationService` (payment matching), `DngWebhookService` (webhook processing), `DngChecksumService` (HMAC checksum)
    - Jobs: `ProcessDngWebhookJob` (async webhook), `ReconcileDngPaymentsJob` (scheduled reconciliation)
    - FormRequest: `CreateDngPaymentFormRequest`
    - Web form: `GET /finance/payments/create` → `GET /finance/payments/{student}/dng-data` → `POST /api/v1/finance/dng/payment-requests` → QR display
    - Admin pages: `GET /finance/dng/payment-requests`, `GET /finance/dng/payment-requests/{dngPaymentRequest}`, `GET /finance/dng/webhook-events`, `GET /finance/dng/webhook-events/{dngWebhookEvent}`
    - Permissions: `create_finance_payments`, `view_finance_dng_payment_requests`, `view_finance_dng_webhook_events`
    - Webhook event list is cross-campus; webhook detail access still validates linked request `campus_code` or orphan payload campus code
    - Webhook flow is inbox-first: controller stores raw callback immediately, queue processing applies checksum/business validation later, and event lifecycle now uses `received`, `processing`, `processed`, `failed_retryable`, `failed_terminal`, `mismatch`, `skipped`
    - Campus mapping: `campuses.dng_code` is the per-campus source of truth for DNG `CampusCode`
    - Replacement rule: same `student + fee_type` keeps only one active unpaid DNG request locally; older unpaid requests move to `cancelled` only after the newer push succeeds
    - Cancelled requests are terminal for webhook/reconciliation processing
    - Student DNG request API now accepts `cancelled` in status filtering
    - `student_code` field is string (MSSV not DB ID)

Verified entry points:

- `bootstrap/app.php`
- `routes/web.php`
- `routes/api.php`
- `resources/js/app.ts`
- `resources/js/ssr.ts`

## 4) Auth and API Baseline

- Student and lecturer v1 routes are protected with Sanctum + actor middleware patterns.
- Identity module refresh endpoints for student/lecturer/parent are protected.
- Token TTL in Identity login/refresh actions is standardized to 8 hours.
- Refresh flow is currently issue-new-token then revoke-current-token.

Open drift retained in code:

- Public `/api/system-config*` routes in `routes/api.php`.
- Finance API routes in `app/Modules/Finance/routes/api.php` use `web` + `auth`.

## 5) Frontend Contract Baseline

Current frontend integration posture:

- `route(...)` helper usage is dominant (`523` matches in `resources/js` search snapshot).
- Literal-path pockets remain (`router.visit('/...')` and direct `/api/...` calls), which can drift during route refactors.

## 6) Ops/Delivery Baseline

- CI workflows exist but are disabled (fully commented out): `deploy.yml`, `lint.yml`, `tests.yml`.
- Docker-first local command wrappers are now documented around `./scripts/dev.sh`; remaining script/compose path drift should be measured against that wrapper flow.
- Deployment/runtime artifacts still expose security risks (hardcoded defaults/credentials and public DB port mapping in production compose files).

## 7) Documentation Baseline

Core baseline docs are maintained in:

- `docs/project-overview-pdr.md`
- `docs/code-standards.md`
- `docs/system-architecture.md`
- `docs/project-roadmap.md`
- `docs/design-guidelines.md`
- `docs/deployment-guide.md` (Production deployment with FrankenPHP, queue workers, scheduler)
- `docs/features/notification/README.md` (Notification V2 module docs)
- `docs/features/finance/tuition-settlement-model-v2.md` (target settlement source-of-truth and migration baseline)

Documented implementation patterns:

- `custom-skills/inertia-filter-table/` — canonical skill for building server-side filtered/sorted/paginated tables with Laravel + Vue 3 + Inertia

## 8) Immediate Documentation Priorities

1. Keep auth matrix and risk statements synchronized with route changes.
2. Track CI/deploy drift as current-state risk until remediated.
3. Tighten source-of-truth discipline (owner/status/last-updated in core docs).
4. Keep unresolved decisions explicit at doc end sections.

## Unresolved Questions

- Should repomix metrics exclude heavy generated/static artifacts for engineering trend reporting?
- What is the formal placement policy for new business logic: `app/Modules/*` vs `app/Services/*`?
