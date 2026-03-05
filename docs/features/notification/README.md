# Notification Docs Index

## Tổng quan

Bộ tài liệu này mô tả cách hoạt động và cách sử dụng Notification feature theo kiến trúc V2 (domain event + outbox + canonical recipient user).

## Tài liệu

- `notification-realtime-architecture.md`: kiến trúc lõi và boundary.
- `broadcasting.md`: project-specific broadcasting rules.
- `notification-broadcasting-setup.md`: setup chi tiết backend/frontend.
- `realtime_setup_guide.md`: quickstart local.
- `huong-dan-realtime-notification.md`: hướng dẫn tích hợp realtime cho Admin/Inertia.
- `huong-dan-realtime-nuxt-spa.md`: hướng dẫn cho Nuxt SPA ngoài hệ.
- `notification-system-deployment.md`: checklist deploy, monitoring, rollback.
- **Ops Monitoring**: See [Notification Ops section](#notification-ops-admin-monitoring) below.

## Manual Notification Send (v2)

Manual notifications use the v2 domain event architecture:

1. Controller validates request with `campus_id`
2. `SendManualNotificationV2Action` creates `DomainEventEnvelope`
3. `PublishDomainEventAction` writes to `notification_event_outbox`
4. Outbox worker processes via `HandleOutboxEventAction`
5. Recipients resolved and filtered by campus via `RecipientResolver`
6. `SendNotificationDeliveryJob` delivers via realtime channel

### Event Name

`manual.notification_sent`

### Payload Structure

```json
{
  "type_key": "manual_notification",
  "recipient_targets": [{"type": "student", "id": 123}],
  "channels": ["realtime"],
  "data": {
    "title": "...",
    "body": "...",
    "category": "system",
    "is_important": false,
    "action_url": "/...",
    "action_text": "View"
  }
}
```

### Configuration

Set `NOTIFICATION_V2_WRITE_MODE=v2` in `.env` (default in config).

To rollback to legacy: `NOTIFICATION_V2_WRITE_MODE=off`

## Notification Ops (Admin Monitoring)

Admin pages for monitoring the v2 domain event notification system. Requires `view_any_notification` permission.

### Pages

| Page | Route | Description |
|------|-------|-------------|
| Outbox | `/admin/notifications/ops/outbox` | Domain events in outbox pattern |
| Outbox Detail | `/admin/notifications/ops/outbox/{id}` | Event details with linked messages/deliveries |
| Deliveries | `/admin/notifications/ops/deliveries` | Delivery attempts per channel |
| Messages | `/admin/notifications/ops/messages` | All notification messages |

### Routes

```
GET  admin.notifications.ops.outbox           # List outbox entries
GET  admin.notifications.ops.outbox.detail    # Outbox entry details
POST admin.notifications.ops.outbox.retry     # Retry failed outbox entry
GET  admin.notifications.ops.deliveries       # List delivery attempts
POST admin.notifications.ops.deliveries.retry # Retry failed delivery
GET  admin.notifications.ops.messages         # List notification messages
```

### Outbox Page

Monitors domain events written to `notification_event_outbox` table.

**Statuses:**
- `pending` - Waiting for processing
- `processing` - Currently being processed
- `dispatched` - Successfully processed, messages created
- `failed` - Processing failed (retryable)

**Filters:** search (event_id/event_name/aggregate_id), status, event_name, date range, has_error

**Sortable columns:** occurred_at, event_name, status, attempts, created_at

**Retry:** Only entries with `failed` or `pending` status. Dispatches `ProcessNotificationOutboxJob`.

### Deliveries Page

Monitors individual delivery attempts per channel (email, realtime).

**Statuses:**
- `pending` - Queued for delivery
- `sent` - Successfully delivered
- `failed` - Delivery failed (retryable)
- `skipped` - Intentionally skipped

**Channels:** `email`, `realtime`

**Filters:** search (event_id/title), status, channel, date range, has_error

**Sortable columns:** created_at, channel, status, attempts, queued_at, sent_at

**Retry:** Only deliveries with `failed` or `pending` status. Dispatches `SendNotificationDeliveryJob`.

### Messages Page

Monitors notification messages created from domain events.

**Filters:** search, status, type_key, read_status, date range

**Sortable columns:** created_at, type_key, status, read_at

### Campus Isolation

All pages filter data by `session('current_campus_id')`. Users only see notifications for their current campus context.

### Architecture

```
NotificationOpsController
├── outbox()      → ListOutboxQuery::handle()
├── outboxDetail()
├── retryOutbox() → RetryOutboxAction::run()
├── deliveries()  → ListDeliveriesQuery::handle()
├── retryDelivery() → RetryDeliveryAction::run()
└── messages()    → ListMessagesQuery::handle()
```

Frontend: Vue 3 + Inertia.js pages in `resources/js/pages/Admin/Notifications/Ops/`

## Quyết định đã chốt trong docs

- Strict campus isolation mặc định theo `event.campus_id`.
- Exception allowlist: system/security + global announcement.
- Canonical recipient: `recipient_user_id`.
- Unresolved recipient: skip + audit + metric alert, không fallback legacy.
- Phase 1 không migrate lịch sử notifications cũ.
