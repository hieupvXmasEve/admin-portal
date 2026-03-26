---
phase: 3
title: 'Webhook event list/detail pages'
status: completed
effort: 3h
---

# Phase 3 — Webhook event pages

## Goal

Expose webhook intake/processing diagnostics without adding recovery actions.

## Files to create

- `resources/js/pages/Finance/Payments/DngWebhookEvents/Index.vue`
- `resources/js/pages/Finance/Payments/DngWebhookEvents/Show.vue`

## Index outline

- same modern server table pattern as settlement page
- cards: total, pending, processed, mismatch/orphan, invalid checksum
- filters: search, processing_status, event_type, checksum_validity, linked_request, date range, sort
- columns: created_at, dng_payment_id, event_type, processing_status, checksum, linked request, processed_at, error, actions

## Show outline

- header: event id + processing badge
- processing summary card: checksum, event type, created/processed timestamps
- linkage card:
    - linked request id/status if present
    - linked canonical payment if present through request
    - orphan badge if absent
- diagnosis card from `error_message`
- normalized payload facts block from raw payload:
    - PaymentId
    - StudentId
    - ItemId
    - Amount
    - CampusCode
    - InvoiceSerialNumber / InvoiceDate when present
- raw headers viewer
- raw payload viewer

## UX rules

- invalid checksum must be high-signal.
- mismatch/orphan copy must say view-only; no retry button.
- linked request/status should deep-link to request detail.

## Acceptance

- operator can diagnose why a callback did not reconcile.
- orphan vs mismatch vs failed vs pending are visually distinct.
- payload facts are readable without digging through raw JSON first.

## Sync-back

- [x] Shipped `Finance/Payments/DngWebhookEvents/Index.vue` with status, checksum, orphan/linkage, and action visibility.
- [x] Shipped `Finance/Payments/DngWebhookEvents/Show.vue` with processing summary, linkage panel, diagnosis block, payload facts, and raw viewers.
- [x] Added deep links between webhook events, payment requests, and canonical payments where available.
