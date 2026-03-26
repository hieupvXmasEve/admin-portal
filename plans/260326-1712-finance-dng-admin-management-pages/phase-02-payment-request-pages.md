---
phase: 2
title: 'Payment request list/detail pages'
status: completed
effort: 3h
---

# Phase 2 — Payment request pages

## Goal

Ship admin visibility for DNG request lifecycle using the modern Finance table pattern.

## Files to create

- `resources/js/pages/Finance/Payments/DngPaymentRequests/Index.vue`
- `resources/js/pages/Finance/Payments/DngPaymentRequests/Show.vue`

## Index outline

- use `FilterPanel`, `FilterSearchInput`, `FilterSelect`, `ServerPaginatedDataTable.vue`
- use `useServerTableQuery` or `useInertiaFilters` consistently with Settlement pattern
- cards: total, pending, paid, failed, bridged
- filters: search, status, has_payment, has_webhook, date range, per_page, sort
- columns: created_at, student, fee_type/item_id, amount, status, dng ids, linked payment, latest webhook, actions

## Show outline

- header: request id, status badge, back link
- cards: student, canonical payment, DNG identifiers, timestamps
- diagnostics callouts:
    - failed push
    - no callback yet
    - paid but not bridged
    - awaiting invoice
- payload viewers:
    - push_payload
    - push_response
    - qr_payload
    - last_callback_payload
- linked webhook table with links to event detail

## UX rules

- keep JSON collapsed/readable; do not render giant payloads inline by default if shared JSON viewer exists.
- badges must distinguish request status from bridge status.
- link to `finance.payments.show` when `payment_id` exists.

## Acceptance

- operator can answer request state without opening DB.
- orphan/missing webhook state is obvious.
- detail page exposes all four payload columns safely.

## Sync-back

- [x] Shipped `Finance/Payments/DngPaymentRequests/Index.vue` with stats, filters, table, and deep links.
- [x] Shipped `Finance/Payments/DngPaymentRequests/Show.vue` with lifecycle cards, payload viewers, and linked webhook list.
- [x] Added operator diagnostics for failed push, missing callback, paid-not-bridged, and awaiting invoice states.
