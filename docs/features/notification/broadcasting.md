# Notification Broadcasting (Project-Specific)

Tài liệu này mô tả cách broadcasting đang được dùng cho tính năng Notification trong Swinx (không phải docs Laravel tổng quát).

## Mục tiêu

- Realtime notification cho đúng người dùng, đúng campus.
- Không phụ thuộc cứng vào provider (Ably / Pusher / Reverb).
- Tách business event khỏi transport qua Notification module V2.

## Channel đang dùng

- Legacy (duy trì tương thích ngắn hạn): `notifications.{userId}`
- V2 canonical: `notify.{campusId}.{recipientUserId}`

## Event realtime V2

- Event class: `App\Modules\Notification\Events\NotificationDeliveryBroadcast`
- Event name: `.NotificationCreated`
- Payload tối thiểu:
    - `id`
    - `title`
    - `message`
    - `data`
    - `type_key`
    - `event_name`
    - `created_at`

## Auth channel

Channel auth nằm ở `routes/channels.php`:

- validate canonical user id
- validate campus boundary
- chỉ cho phép `campusId=0` cho luồng global đã cho phép (dùng cẩn trọng)

## Driver configuration

Backend đọc từ `config/broadcasting.php`:

- `BROADCAST_CONNECTION=ably|pusher|reverb|null`

Frontend Echo config nằm ở `resources/js/lib/echo.ts`, đọc ENV:

- `VITE_BROADCASTER`
- `VITE_BROADCAST_KEY`
- optional socket host/port env

## Queue + scheduler

- Broadcast và delivery đều chạy bất đồng bộ qua queue.
- Outbox dispatcher chạy qua command:
    - `php artisan notifications:process-outbox --limit=100`
- Command đã được schedule mỗi phút trong `routes/console.php`.

## Checklist khi debug realtime

1. `BROADCAST_CONNECTION` đúng chưa.
2. Queue worker có chạy không (`php artisan queue:work`).
3. Scheduler có chạy không (`php artisan schedule:run` / cron).
4. Channel auth pass không (xem `storage/logs/laravel.log`).
5. Frontend đang subscribe đúng channel theo campus/user chưa.

## Lưu ý quan trọng

- Không broadcast trực tiếp từ controller.
- Không gọi provider SDK trực tiếp trong business logic.
- Không bypass outbox cho các event nghiệp vụ V2.
