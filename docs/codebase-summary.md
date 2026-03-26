# Codebase Summary

Last updated: 2026-03-26
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
- Notification V2 module (`app/Modules/Notification/`) implements domain event + outbox pattern with Phase 1 foundation complete:
    - Outbox tables: `notification_event_outbox`, `notification_messages`, `notification_deliveries`
    - Event types in use: `academic.course_stage_changed`, `course_completed`
    - Full audit trail + retry via outbox processor (`notifications:process-outbox` scheduled every minute)
    - Actions: `PublishDomainEventAction`, `DispatchOutboxBatchAction`, `SendManualNotificationV2Action`, retry actions
    - Channels: `EmailChannelAdapter`, `RealtimeChannelAdapter`
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
- Finance operations include student-centric Settlement Worklist at `finance/operations/settlement`; legacy `payments/auto-allocate` UI redirects there.
- Finance generation/migration flows include backfill commands for voucher and scholarship headers + `discount_allocations`. Zero-amount tuition terms no longer create invoices.
- DNG payment gateway integration (2026-03-26):
    - Tables: `dng_payment_requests` (audit), `dng_webhook_events` (webhook audit)
    - Controllers: `DngPaymentController` (API), `DngWebhookController` (webhook receiver)
    - Services: `DngClient` (API comms with checksum), `DngPaymentService` (payment request logic), `DngReconciliationService` (payment matching), `DngWebhookService` (webhook processing), `DngChecksumService` (HMAC checksum)
    - Jobs: `ProcessDngWebhookJob` (async webhook), `ReconcileDngPaymentsJob` (scheduled reconciliation)
    - FormRequest: `CreateDngPaymentFormRequest`
    - Web form: `GET /finance/payments/create` → `GET /finance/payments/{student}/dng-data` → `POST /api/v1/finance/dng/payment-requests` → QR display
    - Permission: `create_finance_payments`
    - Config: `DNG_CAMPUS_CODE` env var mapped to `config('services.dng.campus_code')`, `student_code` field is string (MSSV not DB ID)

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
- Script/compose path drift still present (missing helper scripts; root path assumptions vs `docker/*` files).
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
