# Phase 04 - Dual-Read API/UI Cutover

## Context Links

- Admin API controller: `app/Http/Controllers/Api/NotificationController.php`
- Student API controller: `app/Http/Controllers/Api/V1/Student/NotificationController.php`
- Web popper: `resources/js/components/NotificationPopper.vue`

## Overview

- Priority: High
- Status: in-progress
- Objective: introduce feature-flagged dual-read and keep response compatibility for admin/student UI surfaces.

## Key Insights

- Legacy and v2 have different recipient model assumptions; avoid mixed joins.
- Phase 1 intentionally excludes legacy history migration.

## Requirements

- Functional:
    - read mode flags: `legacy`, `dual_compare`, `v2`
    - preserve response fields expected by existing frontend
    - mark-read/mark-all-read flows work against v2 when enabled
- Non-functional:
    - compatibility first, no visible regression
    - mismatch telemetry in dual compare mode

## Architecture

- Read path abstraction behind query action/service.
- In `dual_compare`, serve legacy response while recording v2 diff metrics.
- In `v2`, query by `recipient_user_id` and campus constraints.

## Related Code Files

- Create:
    - `app/Modules/Notification/Queries/ListNotificationFeedQuery.php`
    - `app/Modules/Notification/Actions/MarkNotificationReadAction.php`
    - `app/Modules/Notification/Actions/MarkAllNotificationsReadAction.php`
    - `app/Modules/Notification/Http/Resources/NotificationFeedResource.php`
- Modify:
    - `app/Http/Controllers/Api/NotificationController.php`
    - `app/Http/Controllers/Api/V1/Student/NotificationController.php`
    - `app/Http/Controllers/Web/Admin/NotificationController.php`
    - `resources/js/components/NotificationPopper.vue`
    - `resources/js/pages/Admin/Notifications/Index.vue`

### Ops Monitoring (COMPLETED 2026-03-04)

- Created:
    - `routes/web/notifications.php` (added ops routes)
    - `app/Modules/Notification/Queries/ListOutboxQuery.php`
    - `app/Modules/Notification/Queries/ListDeliveriesQuery.php`
    - `app/Modules/Notification/Queries/ListMessagesQuery.php`
    - `app/Modules/Notification/Actions/RetryOutboxAction.php`
    - `app/Modules/Notification/Actions/RetryDeliveryAction.php`
    - `app/Modules/Notification/Http/Web/Admin/NotificationOpsController.php`
    - `resources/js/pages/Admin/Notifications/Ops/Outbox.vue`
    - `resources/js/pages/Admin/Notifications/Ops/Deliveries.vue`
    - `resources/js/pages/Admin/Notifications/Ops/Messages.vue`
    - `resources/js/pages/Admin/Notifications/Ops/OutboxDetail.vue`
- Modified:
    - `app/Modules/Notification/Models/NotificationEventOutbox.php` (added messages relation)

## Implementation Steps

1. Add feature flag config and route read strategy through abstraction layer.
2. Map v2 records to existing API resource contracts.
3. Implement mark-read endpoints on v2 tables.
4. Add dual-compare mismatch logs/metrics.
5. Ensure only post-cutover data appears in v2 list (no legacy backfill expectation).

## Todo List

### Ops Monitoring (DONE)

- [x] Create ops routes under `admin/notifications/ops/*`
- [x] Implement ListOutboxQuery, ListDeliveriesQuery, ListMessagesQuery
- [x] Implement RetryOutboxAction and RetryDeliveryAction
- [x] Create NotificationOpsController with list/show/retry endpoints
- [x] Create Outbox, Deliveries, Messages, OutboxDetail Vue pages
- [x] Fix campus scoping and Vue reactivity issues
- [x] Tests passing (38 tests)

### User-Facing Notification Feed (PENDING)

- [ ] Add read-mode feature flags
- [ ] Implement v2 feed query + resources
- [ ] Wire admin/student controllers
- [ ] Wire mark-read/mark-all-read actions
- [ ] Add dual-compare metrics

## Success Criteria

- Admin/student notification screens behave unchanged under legacy mode.
- V2 mode serves only new notifications and preserves expected payload shape.
- Dual compare reports no critical mismatch before full switch.

## Risk Assessment

- Risk: API contract drift breaks frontend.
- Mitigation: resource compatibility snapshots and targeted UI smoke tests.

## Security Considerations

- Enforce actor ownership on read and read-state changes.
- Enforce campus scope in feed queries.

## Next Steps

- Continue to Phase 05 pilot event integration.

## Unresolved Questions

- None.
