# Notification Realtime Architecture (V2)

## 1) Kiến trúc tổng thể

```text
Business Action
  -> after-commit write notification_event_outbox
  -> notifications:process-outbox
  -> map Event -> Intent
  -> policy + recipient resolver
  -> persist notification_messages / notification_deliveries
  -> channel jobs (email/realtime)
  -> realtime broadcast to private channel
```

## 2) Thành phần chính

- **Outbox**: `notification_event_outbox`
- **Message store**: `notification_messages`
- **Delivery store**: `notification_deliveries`
- **Canonical recipient**: `recipient_user_id`
- **Source actor metadata**: `recipient_meta`

## 3) Security boundaries

- Mặc định strict campus isolation theo `event.campus_id`.
- Global exception chỉ cho:
    - `system.security*`
    - `system.announcement.global*`
- Channel auth bắt buộc validate user + campus.

## 4) Channel design

- Legacy: `notifications.{userId}`
- V2 canonical: `notify.{campusId}.{recipientUserId}`

## 5) Idempotency levels

- Event-level: unique `notification_event_outbox.event_id`
- Message-level: unique `(event_id, type_key, recipient_user_id)`
- Delivery-level: unique `(message_id, channel)`

## 6) Unresolved recipients

Khi target không resolve được về `user_id`:

- skip delivery
- audit log `notification.recipient_unresolved`
- metric `notification_recipient_unresolved_total`
- không fallback legacy

## 7) Gating/cutover

Config ở `config/notification.php`:

- `v2_enabled`
- `write_mode`: `off|dual|v2_only`
- `read_mode`: `legacy|dual_compare|v2`

## 8) Realtime payload contract

Event `.NotificationCreated` nên giữ payload ổn định:

- `id`, `title`, `message`, `data`, `type_key`, `event_name`, `created_at`

## 9) Operational points

- Queue worker bắt buộc chạy.
- Scheduler phải chạy để trigger `notifications:process-outbox`.
- Theo dõi backlog/failed để rollback sớm nếu cần.
