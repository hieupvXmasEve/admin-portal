# Realtime Setup Guide (Quickstart)

Quickstart này dùng cho dev cần bật Notification realtime trong local nhanh nhất.

## 1) Cấu hình backend

`.env`:

```env
BROADCAST_CONNECTION=ably
ABLY_KEY=xxxx
QUEUE_CONNECTION=database

NOTIFICATION_V2_ENABLED=true
NOTIFICATION_V2_WRITE_MODE=dual
NOTIFICATION_V2_READ_MODE=legacy
```

## 2) Cấu hình frontend

`.env`:

```env
VITE_BROADCASTER=${BROADCAST_CONNECTION}
VITE_BROADCAST_KEY=${ABLY_KEY}
```

## 3) Chạy services

```bash
php artisan migrate
php artisan queue:work
php artisan schedule:work
pnpm run dev
```

## 4) Trigger và verify

```bash
php artisan notifications:process-outbox --limit=100
```

Verify:

- DB có row ở `notification_messages`, `notification_deliveries`
- FE nhận `.NotificationCreated`
- NotificationPopper hiển thị realtime

## 5) Channel key expected

- `notify.{campusId}.{recipientUserId}` (V2)
- `notifications.{userId}` (legacy compatibility)

## 6) Nếu không hoạt động

- check `storage/logs/laravel.log`
- check queue worker còn chạy
- check channel auth ở `routes/channels.php`
- check user/campus context từ Inertia page props
