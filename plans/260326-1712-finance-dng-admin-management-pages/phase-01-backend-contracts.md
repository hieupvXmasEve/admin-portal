---
phase: 1
title: 'Backend contracts: routes, permissions, controllers, query API'
status: completed
effort: 3h
---

# Phase 1 — Backend contracts

## Goal

Lay down thin web entrypoints and stable query contracts. No business-logic changes.

## Files to modify

- `app/Modules/Finance/routes/web.php`
- `config/permission.php`
- `resources/js/constants/menu-sidebar.ts`

## Files to create

- `app/Modules/Finance/Http/Web/Admin/DngPaymentRequestController.php`
- `app/Modules/Finance/Http/Web/Admin/DngWebhookEventController.php`
- `app/Modules/Finance/Queries/Dng/ListDngPaymentRequestsQuery.php`
- `app/Modules/Finance/Queries/Dng/GetDngPaymentRequestDetailsQuery.php`
- `app/Modules/Finance/Queries/Dng/ListDngWebhookEventsQuery.php`
- `app/Modules/Finance/Queries/Dng/GetDngWebhookEventDetailsQuery.php`
- optional FormRequests if filter validation grows beyond inline clarity

## Route outline

- add `/finance/dng/payment-requests` index/show
- add `/finance/dng/webhook-events` index/show
- place static segments before wildcard params
- gate with read permissions only

## Query contract outline

### Payment request list returns

- `items`
- `stats`
- `filters`

Each row includes only light fields:

- ids, student summary, campus_code, fee_type, amount, status
- dng ids
- linked payment summary
- linked/latest webhook summary

### Payment request detail returns

- core metadata
- student/payment summary
- timeline-ish timestamps
- payload blobs
- linked webhook events list

### Webhook event list returns

- `items`
- `stats`
- `filters`

Each row includes:

- ids, dng_payment_id, event_type, processing_status, checksum flag
- linked request summary or orphan flag
- processed timestamp, short error

### Webhook event detail returns

- processing metadata
- request/payment linkage summary
- headers payload
- event payload
- extracted match keys for readability

## Implementation notes

- eager-load only what index pages need.
- no JSON blobs in list payload.
- whitelist sort columns.
- apply campus scope first, then filters.
- return arrays, not models.

## Acceptance

- routes exist with correct `finance.*` naming.
- permissions are explicit in `config/permission.php`.
- menu can reference new routes.
- query classes define stable props for Vue pages.

## Sync-back

- [x] Added `finance.dng.payment-requests.*` and `finance.dng.webhook-events.*` web routes.
- [x] Added `view_finance_dng_payment_requests` and `view_finance_dng_webhook_events` permissions.
- [x] Wired Finance sidebar entries for both DNG admin pages.
- [x] Created thin controllers and 4 DNG query classes for index/show contracts.

## Risks

- route ordering drift.
- overfetching relations.
- unclear campus scope source.
