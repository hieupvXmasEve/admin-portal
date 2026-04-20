# Project Roadmap

Last updated: 2026-04-21  
Owner: Platform Team  
Status: Active execution baseline  
Horizon: next 2-3 quarters

## 1) Objectives

- Stabilize API security and auth consistency.
- Restore delivery confidence (CI + deployment path normalization).
- Reduce contract drift between backend routes and frontend integrations.
- Keep documentation synchronized with code changes.

## 2) Current Baseline

- Platform is operational with hybrid architecture (`app/Modules/*` + `app/Services/*`).
- Frontend filter stack remains hybrid (`useInertiaFilters`, legacy `useFilters`/`useTableFilters`, and `useServerTableQuery`).
- Auth hardening landed for student/parent/lecturer actor middleware and protected refresh routes.
- Token TTL is aligned to 8 hours across current Identity login/refresh actions.
- Critical unresolved risks remain:
    - public `/api/system-config*`
    - finance API auth model drift
    - disabled CI workflows
    - deployment/script/security drift

Recent activity through 2026-04-21:

- 2026-04-21: EGC finance operations baseline expanded
    - New EGC ops pages now cover block result review, retake adjustments, and unused EGC carry-forward
    - EGC carry-forward uses `egc_blocks` + `finance_charge_id` as consume truth and releases unused paid EGC balance back to unapplied cash without auto-reallocation
    - Retake target selection now requires a later retake block charge for the same student/level
    - EGC ops list pages are now campus-scoped by `current_campus_id` session context:
        - `/finance/egc/block-results`
        - `/finance/egc/retake-adjustments`
        - `/finance/egc/carry-forward`
    - `egc:backfill-blocks` now includes historical students with EGC registrations/charges regardless of current status, so deferred/dropout FALL2025 cases can be rebuilt
    - Backfill no longer creates new `egc_block` rows from unmatched active charges; extra charges are reported for repair instead
- 2026-04-16: DNG campus mapping and replacement rules updated
    - `campuses.dng_code` added as nullable per-campus DNG mapping
    - DNG create/reconcile flows now resolve external `CampusCode` from `campuses.dng_code`
    - Campus CRUD now exposes `dng_code`
    - Same `student + fee_type` DNG replacements now cancel older unpaid requests only after the new push succeeds
    - Late webhook/reconciliation events for cancelled requests are skipped
    - Local docs/rules now prefer Docker-first commands via `./scripts/dev.sh`

- 2026-03-26: DNG payment gateway integration completed
    - Payment Creation UI at `/finance/payments/create`
    - Student data endpoint: `GET /finance/payments/{student}/dng-data`
    - API payment request: `POST /api/v1/finance/dng/payment-requests` with checksum validation
    - Webhook receiver: `POST /api/v1/finance/dng/webhook`
    - Admin audit pages: `/finance/dng/payment-requests`, `/finance/dng/webhook-events`
    - Audit tables: `dng_payment_requests`, `dng_webhook_events`
    - Permission gates: `create_finance_payments`, `view_finance_dng_payment_requests`, `view_finance_dng_webhook_events`
    - Fixed: `DngClient::insertNewRecord()` uses `student_code` (string MSSV) not `student_id` (int DB PK)
- 2026-03-25: Finance settlement v2 landed with invoice-line truth model
    - `PaymentApplication` replaces `payment_allocations` as cash application truth
    - `DiscountAllocation` table for line-level discount allocation
    - `InvoiceLine` status/void lifecycle (active|void, voided_at, void_reason)
    - New services: `SettlementService`, `PaymentService`, `FinanceChargeService`
    - New actions: `VoidFinanceChargeAction`, `AllocatePaymentAction`, `AutoAllocatePaymentsAction`
    - Support: `StudentChargeTimingResolver` for EGC vs Tuition billing rules
- 2026-03-25: Finance dashboards aligned with settlement v2 rules
    - Settlement Worklist at `finance/operations/settlement`
    - Dashboard at `finance/operations/dashboard` with gross/discount/paid/outstanding metrics
    - Zero-amount tuition terms no longer create invoices
- 2026-03-23: Academic progression course stage changes
    - `COURSE_STAGE_CHANGED` event type in `academic_progression_events`
    - Notifications published via V2 outbox on stage transition
    - Student fee statement improved for staff view
- 2026-03-20: Voucher workflows simplified
    - Removed import/delete UI flows
    - Retained: Create, Edit, Show (with inline apply card), Apply
    - Discount flows via `invoice_discounts` + `discount_allocations`
- Before 2026-03-20:
    - Notification V2 Phase 1 foundation: domain event + outbox, ops monitoring, retry
    - Production deployment guide with FrankenPHP, queue worker, scheduler
    - `inertia-filter-table` skill documented as canonical pattern

## 3) Phase Plan

### Phase 0: Core Documentation Alignment

Status: Completed  
Completed date: 2026-02-25

Outcome:

- Core docs aligned to current code-verified state.
- Removed aspirational phrasing from quality/security posture.

### Phase 1: Deploy/Script Normalization

Status: In progress  
Target: 1-2 sprints

Scope:

- choose one canonical deploy entrypoint
- align script references to actual `docker/*` layout
- remove references to missing helper scripts

Exit criteria:

- no broken script references in normal dev/deploy paths
- deployment guide maps exactly to runnable script flow

### Phase 2: CI Reactivation

Status: Planned  
Target: after Phase 1

Scope:

- uncomment and modernize `.github/workflows/*`
- enforce minimum checks on PRs

Minimum gate target:

- lint
- type-check
- backend tests

Exit criteria:

- required checks run automatically and block on failure

### Phase 3: API Security Hardening

Status: Planned  
Target: overlaps with Phase 2 when feasible

Scope:

- secure or relocate public `system-config` routes
- resolve finance API auth drift (`web` + `auth` vs Sanctum actor model)
- document stable auth matrix by route group

Exit criteria:

- no high-risk public config mutation/read routes without explicit approval rationale
- finance API auth model decision implemented and documented

### Phase 4: Frontend Contract Stabilization

Status: Planned  
Target: ongoing

Scope:

- reduce literal URL pockets in frontend pages/composables
- standardize route helper usage and API wrapper usage in high-churn pages

Exit criteria:

- measurable reduction in literal path usage on critical flows
- route contract changes require single-source updates

### Phase 5: Architecture Consolidation

Status: Planned  
Target: ongoing

Scope:

- define explicit policy for module-domain folders vs shared service layer
- apply policy incrementally on touched hotspots

Exit criteria:

- reduced placement ambiguity for new domain logic
- documented ownership by domain/module

### Phase 6: Finance Settlement Cutover

Status: Settlement v2 landed (2026-03-25)
Target: active

Scope:

- remove runtime dependence on legacy `payment_allocations` ✓ (2026-03-25)
- standardize invoice-line settlement across fee summary, settlement worklist, generate charges, and dashboard ✓ (2026-03-25)
- backfill voucher/scholarship data into `invoice_discounts` + `discount_allocations` ✓ (backfill commands added 2026-03-25)
- finish snapshot reconciliation for legacy invoices (in progress, backfill commands available)

Exit criteria:

- finance runtime reads no longer depend on `payment_allocations` ✓
- discount truth is line-level via `discount_allocations` ✓
- dashboard/worklist/invoice views agree on gross, discount, paid, outstanding ✓
- zero-amount tuition terms no longer create invoices ✓

Remaining work:

- legacy read model convergence (if required for parallel systems)
- backfill validation and reconciliation for existing data

## 4) Dependencies

- Phase 1 before Phase 2
- Phase 2 before strict merge-gate enforcement policy
- Phase 3 and Phase 4 can overlap after baseline CI is active
- Documentation updates are required in every phase
- Phase 6 depends on migration command rollout and snapshot reconciliation

## 5) Tracking Metrics

- CI status: active/inactive + pass rate
- Script drift count: missing scripts/path mismatches
- API risk count: unresolved high-risk auth exposures
- Frontend contract drift: literal path usage in high-risk areas
- Docs hygiene: stale claim/link count in core docs

## 6) Immediate Decisions

1. Canonical deploy script and environment contract.
2. Minimum required CI gates for merge.
3. Final auth model for finance API routes.
4. Ownership and timeline for public system-config hardening.
5. Finance settlement cutover order for remaining legacy read models.

## Unresolved Questions

- Who is accountable for Phase 1 execution window and sign-off?
- Which API endpoints are external-facing contracts vs internal web support APIs?
- What is the acceptable grace period between code change and required doc update?
