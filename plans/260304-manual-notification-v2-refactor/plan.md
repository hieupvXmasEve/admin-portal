---
title: 'Manual Notification Send - V2 Domain Event Refactor'
description: 'Refactor manual notification send to use v2 outbox pattern with campus isolation'
status: in-progress
priority: P1
effort: 4h
branch: dev
tags: [laravel12, notifications, outbox, campus-isolation, refactoring]
created: 2026-03-04
updated: 2026-03-04
---

# Plan Overview

Refactor `NotificationController::send()` and `SendManualNotificationAction` to use the new v2 domain event architecture with outbox pattern, replacing the legacy direct-model-create approach.

## Problem Statement

| Issue | Legacy | V2 Target |
|-------|--------|-----------|
| Transaction coupling | Notification created inside `DB::transaction` | Outbox write only, send after commit |
| No idempotency | Duplicate requests = duplicate notifications | `event_id` unique constraint |
| No campus isolation | Searches all campuses | Filter by `session('current_campus_id')` |
| Synchronous | Boot event triggers broadcast immediately | Async via worker + job queue |

## Architecture Flow

```
Legacy:
Controller → SendManualNotificationAction → DB::transaction → $notifiable->notifications()->create()

V2:
Controller → SendManualNotificationV2Action → DomainEventEnvelope → PublishDomainEventAction::run()
   ↓ (outbox worker)
HandleOutboxEventAction → EventIntentMapper → RecipientResolver → PersistIntentAction → SendNotificationDeliveryJob
```

## Phases Summary

| Phase | Effort | Files Changed | Description | Status |
|-------|--------|---------------|-------------|--------|
| 01 | 1.5h | 2 new, 1 modified | Backend v2 action + EventIntentMapper | ✅ completed |
| 02 | 1h | 2 modified | Controller + Request campus filtering | ✅ completed |
| 03 | 1h | 1 modified | Frontend campus-aware UI | ✅ completed |
| 04 | 0.5h | 1 deleted | Cleanup legacy action | ⏸️ deferred |

## Completion Notes

**Date:** 2026-03-04  
**Tests:** 8/8 passed

### Files Changed in Implementation

| File | Action |
|------|--------|
| `app/Modules/Notification/Actions/SendManualNotificationV2Action.php` | Created |
| `app/Modules/Notification/Support/EventIntentMapper.php` | Modified (added manual event mapping) |
| `app/Http/Controllers/Web/Admin/NotificationController.php` | Modified (refactored with campus filtering) |
| `app/Http/Requests/Notification/SendManualNotificationRequest.php` | Modified (added campus validation) |
| `config/notification.php` | Modified (write_mode default to v2) |
| `resources/js/pages/Admin/Notifications/Send.vue` | Modified (added campus context) |
| `tests/Unit/Notification/SendManualNotificationV2ActionTest.php` | Created |

### Phase 04 Deferral

Legacy action cleanup deferred pending:
- 1+ week production validation of v2 mode
- No rollback triggers
- Healthy outbox processing metrics

## Dependencies

- **Required**: Phase 01-03 from `plans/260303-notification-module-domain-event-phase-1/` (completed)
- Phase 01 before Phase 02
- Phase 02 before Phase 03
- Phase 03 before Phase 04

## Success Criteria

1. Manual notifications route through outbox with unique `event_id`
2. Recipient search filters by current campus
3. `campus_id` required in request payload
4. Legacy action removed after cutover validation
5. All tests pass including new v2 action tests

## Rollback Strategy

```php
// config/notification.php
'manual_notification_mode' => env('MANUAL_NOTIFICATION_MODE', 'v2'), // 'legacy' | 'v2'

// Controller checks this to route to legacy or v2 action
```

## Unresolved Questions

1. **Email channel**: Should manual notifications support email or realtime-only? (Recommend: realtime-only for now)
2. **Dual-write pilot**: Need dual-write period? (Recommend: No, v2 only since outbox is idempotent)
