# Notification Broadcasting Setup

## Mục đích

Thiết lập hạ tầng broadcasting cho Notification V2, hỗ trợ Ably/Pusher/Reverb.

## 1) Backend env tối thiểu

```env
BROADCAST_CONNECTION=ably
ABLY_KEY=xxxx

QUEUE_CONNECTION=database
```

Nếu dùng Reverb/Pusher thì thay bằng bộ biến tương ứng trong `config/broadcasting.php`.

## 2) Frontend env tối thiểu

```env
VITE_BROADCASTER=${BROADCAST_CONNECTION}
VITE_BROADCAST_KEY=${ABLY_KEY}
```

Optional:

```env
VITE_SOCKET_HOST=
VITE_SOCKET_PORT=
```

## 3) Điểm cấu hình trong code

- Backend: `config/broadcasting.php`
- Channel auth: `routes/channels.php`
- Echo init: `resources/js/lib/echo.ts`
- FE subscribe logic: `resources/js/composables/useRealtimeNotifications.ts`

## 4) Commands cần chạy

```bash
php artisan migrate
php artisan queue:work
php artisan notifications:process-outbox --limit=100
```

Khi chạy bình thường, command outbox sẽ do scheduler gọi mỗi phút (`routes/console.php`).

## 5) Verification nhanh

1. Tạo 1 domain event vào outbox.
2. Chạy outbox command.
3. Kiểm tra:
    - `notification_messages` có row
    - `notification_deliveries` có `realtime`
4. Mở UI đúng user/campus để xác nhận event tới client.

## 6) Troubleshooting

- Không có broadcast:
    - check queue worker
    - check broadcast driver env
    - check channel auth logs
- Có message DB nhưng không realtime:
    - check `notification_deliveries.status`
    - check frontend subscribe channel key
- Bị leak cross-campus:
    - kiểm tra auth logic ở `routes/channels.php`
    - kiểm tra `campus_id` trong event/message
