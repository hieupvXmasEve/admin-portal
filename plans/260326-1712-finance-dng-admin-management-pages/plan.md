---
title: 'Finance DNG Admin Management Pages'
description: 'Add read-focused admin list/detail pages for DNG payment requests and webhook events inside Finance.'
status: completed
priority: P2
effort: 11h
branch: dev
tags: [plan, finance, dng, admin, inertia, vue, laravel]
created: 2026-03-26
---

# Finance DNG Admin Management Pages Plan

## Overview

Add internal admin visibility for `dng_payment_requests` and `dng_webhook_events` in Finance. Keep scope read-heavy: list, filter, inspect, cross-link. No retry/reprocess/edit workflow in this phase.

## Evidence Summary

- Finance web routes already live in `app/Modules/Finance/routes/web.php` under `/finance/*` and `finance.*` names.
- Existing admin web pattern is controller -> query -> Inertia page, e.g. `PaymentController`, `ListPaymentsQuery`, `BillingSettlementController`, `ListSettlementWorklistQuery`.
- Existing modern table pattern is `resources/js/pages/Finance/Operations/Settlement.vue` with filter components + `ServerPaginatedDataTable.vue`.
- Finance menu config lives in `resources/js/constants/menu-sidebar.ts`; current payment pages sit under "Billing Operations".
- Permissions are centralized in `config/permission.php` under `finances`.
- DNG models expose required admin fields now:
    - `DngPaymentRequest`: status, campus_code, student/payment links, `push_payload`, `push_response`, `qr_payload`, `last_callback_payload`, `error_message`.
    - `DngWebhookEvent`: payload, headers, checksum validity, `processing_status`, optional `dng_payment_request_id`, `error_message`.
- DNG webhook processing can end in orphan/mismatch/failed/pending states by design (`DngWebhookService`, `ProcessDngWebhookJob`).

## Goal

Give finance admins one auditable backoffice surface to answer:

1. Was a DNG request created/pushed/paid/bridged?
2. Did the webhook arrive, validate, and match a local request?
3. If not, is it orphan, mismatch, invalid checksum, or async pending?

## Recommended Architecture

### Routes

Keep under existing Finance web module. Add a dedicated `dng` subgroup, still inside `/finance`:

- `GET /finance/dng/payment-requests` -> `finance.dng.payment-requests.index`
- `GET /finance/dng/payment-requests/{dngPaymentRequest}` -> `finance.dng.payment-requests.show`
- `GET /finance/dng/webhook-events` -> `finance.dng.webhook-events.index`
- `GET /finance/dng/webhook-events/{dngWebhookEvent}` -> `finance.dng.webhook-events.show`

Reason: avoids overloading `finance.payments.*`, but keeps DNG clearly inside Finance and leaves room for future gateway-specific tooling.

### Controllers

Create thin admin web controllers only:

- `app/Modules/Finance/Http/Web/Admin/DngPaymentRequestController.php`
- `app/Modules/Finance/Http/Web/Admin/DngWebhookEventController.php`

Methods:

- `index(Request $request, ListDngPaymentRequestsQuery $query)`
- `show(DngPaymentRequest $dngPaymentRequest, GetDngPaymentRequestDetailsQuery $query)`
- `index(Request $request, ListDngWebhookEventsQuery $query)`
- `show(DngWebhookEvent $dngWebhookEvent, GetDngWebhookEventDetailsQuery $query)`

Keep controllers adapter-only. Validation can stay request-level if simple, but prefer FormRequest once filters exceed trivial search/select inputs.

### Queries

Add read-only queries under DNG context:

- `app/Modules/Finance/Queries/Dng/ListDngPaymentRequestsQuery.php`
- `app/Modules/Finance/Queries/Dng/GetDngPaymentRequestDetailsQuery.php`
- `app/Modules/Finance/Queries/Dng/ListDngWebhookEventsQuery.php`
- `app/Modules/Finance/Queries/Dng/GetDngWebhookEventDetailsQuery.php`

Rules:

- campus-scope by current campus when available.
- eager-load `student`, `payment`, `webhookEvents`, `dngPaymentRequest` as needed.
- whitelist sort columns.
- paginate with `withQueryString()`.
- return normalized arrays only; do not expose raw models to Vue.

### Pages

Use `Finance/Payments/*` naming for operator discoverability, but isolate DNG sub-area:

- `resources/js/pages/Finance/Payments/DngPaymentRequests/Index.vue`
- `resources/js/pages/Finance/Payments/DngPaymentRequests/Show.vue`
- `resources/js/pages/Finance/Payments/DngWebhookEvents/Index.vue`
- `resources/js/pages/Finance/Payments/DngWebhookEvents/Show.vue`

Reason: matches existing payment page naming, avoids inventing a parallel top-level UI taxonomy.

## Page Contracts

### 1. DNG Payment Requests Index

Primary use: operator worklist / reconciliation visibility.

Filters:

- `search` (student_code, item_id, dng_payment_id, dng_transaction_id)
- `status`
- `has_payment` (`all|yes|no`)
- `has_webhook` (`all|yes|no`)
- `date_range` or `created_from`/`created_to`
- `sort`, `direction`, `per_page`

Columns:

- created_at
- student (name + code)
- campus_code
- fee_type
- item_id
- amount
- status
- dng_payment_id / transaction_id
- bridged payment badge/link
- latest webhook processing snapshot
- actions -> View

Summary cards:

- total requests in scope
- pending / qr_ready / paid_uninvoiced / paid_invoiced / failed counts
- bridged count

### 2. DNG Payment Request Detail

Sections:

- core metadata: ids, campus, student, payment link, timestamps
- status timeline summary based on existing fields (`created_at`, `paid_at`, `invoice_date`, `updated_at`)
- request/response payload cards: `push_payload`, `push_response`, `qr_payload`, `last_callback_payload`
- linked webhook events table (latest first)
- linked canonical payment summary if `payment_id` exists
- operator alerts: no webhook yet, failed push, paid but unbridged, orphan callbacks linked later, etc.

### 3. DNG Webhook Events Index

Primary use: intake/debug queue.

Filters:

- `search` (dng_payment_id, payload_hash, request id, student code from payload if cheap)
- `processing_status`
- `event_type`
- `checksum_validity` (`all|valid|invalid`)
- `linked_request` (`all|linked|orphan`)
- date range
- sort/direction/per_page

Columns:

- created_at
- dng_payment_id
- event_type
- processing_status
- checksum badge
- linked request id/status or orphan badge
- processed_at
- concise error_message
- actions -> View

Summary cards:

- total events in scope
- pending
- processed
- mismatch/orphan
- invalid checksum / failed

### 4. DNG Webhook Event Detail

Sections:

- processing overview: status, checksum, event type, created/processed timestamps
- linkage panel: matched request + canonical payment if available
- mismatch/orphan diagnosis block from `error_message`
- headers JSON viewer
- payload JSON viewer
- request match hints: `PaymentId`, `ItemId`, `StudentId`, amount

## Permissions + Menu

### Recommended permissions

Add 2 read permissions, not reuse broad payment permissions:

- `view_finance_dng_payment_requests`
- `view_finance_dng_webhook_events`

Why:

- DNG data contains raw callback payloads/headers and failure diagnostics; tighter read boundary than generic payment list.
- Lets ops/support access DNG diagnostics without granting create/import/allocate powers.

Optional if team wants simpler RBAC: map both pages to existing `view_finance_payments`. Not recommended long-term.

### Menu placement

Add under existing Finance > "Billing Operations" group near `Payments`:

- Payments
- DNG Payment Requests
- DNG Webhook Events

Do not create a new top-level Finance section yet. Scope too small.

## Key Decisions

- **Separate DNG route namespace, shared Payments page namespace**: clean URLs, familiar page foldering.
- **List + detail for both entities**: enough for investigation; no mutation UI.
- **Read-only phase**: no retry, requeue, relink, or payload reprocess action now.
- **Modern table pattern only**: use `FilterPanel` + `ServerPaginatedDataTable.vue`, not older ad-hoc table pattern.
- **Payloads only on detail pages**: keep list payload light, avoid huge Inertia responses.

## Risks

- **Campus filtering ambiguity**: `DngPaymentRequest` stores `campus_code`, but current app campus scoping elsewhere often uses `student.campus_id`. Need one consistent filter rule.
- **Heavy JSON props**: payload fields can bloat response size if included in list queries.
- **Search over JSON payload**: avoid broad JSON search in v1; too expensive, weak indexing.
- **Status semantics**: request status and webhook processing status are related but not identical; UI must not imply causality where none exists.
- **Orphan/mismatch interpretation**: operators may expect fix actions. Need clear copy: view-only diagnostics in this phase.

## Test Strategy

- Feature tests for 4 pages: auth, permission, Inertia component, base payload.
- Query-level tests for filter combinations, campus scoping, orphan/linked states, checksum status.
- Minimal frontend type safety only; no special frontend behavior beyond filters/table/detail rendering.

## Implementation Sync-Back

- DNG admin web surface shipped: `finance.dng.payment-requests.*` and `finance.dng.webhook-events.*`.
- New granular read permissions shipped and Finance menu wired for both pages.
- Query + Inertia contracts shipped for both entities, including detail payload viewers and cross-links.
- Focused verification landed in `tests/Feature/Finance/DngAdminPagesTest.php` for all 4 pages plus webhook campus-scope coverage.

## Phase Status / Progress

| Phase | Focus                                                          | Status    | Effort | Link                                                                                                                                          |
| ----- | -------------------------------------------------------------- | --------- | -----: | --------------------------------------------------------------------------------------------------------------------------------------------- |
| 01    | Backend contracts: routes, permissions, controllers, query API | completed |     3h | [phase-01](/Users/hunt2412/hieupvdev/project/swinx/plans/260326-1712-finance-dng-admin-management-pages/phase-01-backend-contracts.md)        |
| 02    | Payment request list/detail read model                         | completed |     3h | [phase-02](/Users/hunt2412/hieupvdev/project/swinx/plans/260326-1712-finance-dng-admin-management-pages/phase-02-payment-request-pages.md)    |
| 03    | Webhook event list/detail read model                           | completed |     3h | [phase-03](/Users/hunt2412/hieupvdev/project/swinx/plans/260326-1712-finance-dng-admin-management-pages/phase-03-webhook-event-pages.md)      |
| 04    | Verification, permission wiring, rollout notes                 | completed |     2h | [phase-04](/Users/hunt2412/hieupvdev/project/swinx/plans/260326-1712-finance-dng-admin-management-pages/phase-04-verification-and-rollout.md) |

Current progress: 4/4 phases complete (100%).

## Success Criteria

- Admin can list/filter/open DNG payment requests and webhook events from Finance UI.
- Payment request detail makes request lifecycle and canonical payment linkage obvious.
- Webhook event detail makes checksum validity, processing state, and orphan/mismatch causes obvious.
- New permissions and menu entries are explicit and minimal.
- No mutation-heavy workflow introduced.

## Unresolved Questions

None.
