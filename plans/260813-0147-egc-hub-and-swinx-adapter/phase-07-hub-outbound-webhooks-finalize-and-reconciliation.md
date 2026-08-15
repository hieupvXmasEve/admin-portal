---
phase: 7
title: "Hub Outbound Webhooks, Finalize Block, Reconciliation"
status: pending
priority: P1
effort: "6d"
dependencies: [3, 6]
---

# Phase 7: Hub Outbound Webhooks, Finalize Block, Reconciliation

## Overview
Deliver events to consumers reliably (webhooks + replay), gate block results behind permissioned finalize, detect silent drift nightly.

## Requirements
- Functional:
  - Webhook subscriptions per school (url, secret, event types); HMAC-SHA256 signature header; retry with backoff; dead-letter after N failures visible on monitor with re-push button
  - Finalize block: user with `finalize-block` permission reviews class results (scores present, attendance complete) → finalize → emits `block.completed` per student with block result payload (level, block number, attendance rate, component scores). Before finalize, NO `block.completed` exists for consumers
  - Un-finalize (same permission, audit-logged) for corrections before schools consume — warning shown if webhook already delivered
  - Nightly reconciliation: per school, counts + checksums (active enrollments, attendance rows per block, finalized results) exposed at `GET /v1/reconciliation` for consumers to compare; hub-side alert when a school's adapter stops acking webhooks
  - Sync monitor dashboard: Horizon + custom page (per-school last event delivered, failures, dead letters, key last_used)
- Non-functional: webhook delivery is queue-based, ordered per school (FIFO queue per consumer to avoid out-of-order materialization)

## Architecture
`webhook_endpoints` (school, url, secret, events[]), `webhook_deliveries` (event_id, endpoint_id, status, attempts, last_error). Delivery job chain reads event log — webhooks are a *projection* of the event log, so replay (`GET /v1/events?since=`, phase 3) and webhooks never diverge. `attendance_rate` computed at finalize from membership-interval-aware attendance.

## Related Code Files (egc-hub/)
- Create: `app/Models/{WebhookEndpoint,WebhookDelivery,BlockFinalization}.php` + migrations
- Create: `app/Jobs/DeliverWebhookJob.php`; `app/Services/{WebhookDispatcher,BlockFinalizeService,AttendanceRateCalculator,ReconciliationService}.php`
- Create: `app/Console/Commands/RunNightlyReconciliation.php` (scheduled)
- Create: `app/Http/Controllers/Admin/{WebhookEndpointController,BlockFinalizeController,SyncMonitorController}.php` + Vue pages
- Create: `app/Http/Controllers/Api/V1/ReconciliationController.php`
- Create: Pest tests: HMAC verify, retry/dead-letter, per-school ordering, finalize gates emission, rate math across transfers, unfinalize audit

## Implementation Steps
1. Webhook endpoint CRUD + HMAC signing + delivery job with backoff (1m/5m/30m/2h/dead).
2. Finalize flow: readiness checklist (unpulled scores? unmarked sessions?) → confirm → BlockFinalization row → `block.completed` events.
3. AttendanceRateCalculator (excused=absent, membership intervals).
4. ReconciliationService + nightly command + API surface + monitor alerts.
5. Monitor dashboard page consolidating Horizon stats + delivery health.

## Success Criteria
- [ ] Consumer with wrong secret rejects verified; tampered payload detectable
- [ ] Kill consumer 3 days → dead letters accumulate → re-push + `events?since=` replay recovers all, in order
- [ ] No `block.completed` reachable before finalize; unfinalize logged with warning
- [ ] Nightly reconciliation flags an artificially deleted attendance row

## Risk Assessment
- Out-of-order delivery corrupting school state → per-school FIFO queue + consumers upsert idempotently (contract requirement documented in OpenAPI).
