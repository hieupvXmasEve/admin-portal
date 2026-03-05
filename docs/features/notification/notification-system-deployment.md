# Notification System Deployment Guide (V2)

## 1) Scope

Deploy vận hành Notification V2 gồm:

- DB tables mới
- outbox processor
- queue workers
- realtime broadcasting

## 2) Preconditions

- Migrate xong DB.
- Queue infra hoạt động (`database` hoặc provider khác).
- Broadcast provider configured (Ably/Pusher/Reverb).

## 3) Env checklist

```env
NOTIFICATION_V2_ENABLED=true
NOTIFICATION_V2_WRITE_MODE=dual
NOTIFICATION_V2_READ_MODE=legacy

QUEUE_CONNECTION=database
BROADCAST_CONNECTION=ably
ABLY_KEY=xxxx

VITE_BROADCASTER=${BROADCAST_CONNECTION}
VITE_BROADCAST_KEY=${ABLY_KEY}
```

## 4) Release sequence (recommended)

1. Deploy code + run migrate.
2. Start queue workers.
3. Ensure scheduler cron active.
4. Enable `NOTIFICATION_V2_ENABLED=true`, `WRITE_MODE=dual`.
5. Monitor metrics/logs.
6. Khi ổn định, chuyển `READ_MODE=dual_compare` rồi `v2`.

## 5) Runtime processes

- Queue worker:

```bash
php artisan queue:work --sleep=1 --tries=3
```

- Scheduler cron:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

- Manual outbox run (debug):

```bash
php artisan notifications:process-outbox --limit=100
```

## 6) Health & monitoring

Theo dõi tối thiểu:

- số row `pending` trong outbox
- số `notification_deliveries.status=failed`
- metric `notification_recipient_unresolved_total`
- độ trễ từ `occurred_at` tới `sent_at`

## 7) Rollback

Rollback nhanh không cần rollback schema:

```env
NOTIFICATION_V2_WRITE_MODE=off
NOTIFICATION_V2_READ_MODE=legacy
```

Sau đó kiểm tra queue drain + event duplication.

## 8) Security hardening

- Bảo vệ secret broadcast key.
- Enforce TLS production.
- Không expose payload nhạy cảm qua realtime.
- Kiểm tra channel auth theo campus boundary.
