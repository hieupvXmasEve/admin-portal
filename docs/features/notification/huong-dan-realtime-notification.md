# Hướng dẫn Realtime Notification (Admin/Inertia)

## 1) Luồng hoạt động hiện tại

1. Module nghiệp vụ emit domain event (sau commit).
2. Event được ghi vào `notification_event_outbox`.
3. Worker xử lý outbox, map thành intent.
4. Resolve recipient về `recipient_user_id`.
5. Persist `notification_messages` + `notification_deliveries`.
6. Dispatch channel job (`email` / `realtime`).
7. FE nhận event `.NotificationCreated` qua Echo.

## 2) FE đang subscribe kênh nào

Composable: `resources/js/composables/useRealtimeNotifications.ts`

- Legacy: `notifications.{userId}`
- V2: `notify.{campusId}.{userId}` (chỉ subscribe khi có campus id hợp lệ)

Component dùng thực tế: `resources/js/components/NotificationPopper.vue`

## 3) Cách dùng trong page/layout

```vue
<script setup lang="ts">
import { useRealtimeNotifications } from '@/composables/useRealtimeNotifications';
import { usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';

const page = usePage<SharedData>();
const userId = page.props.auth?.user?.id;
const campusId = page.props.auth?.current_campus_id ?? null;

useRealtimeNotifications(userId, campusId);
</script>
```

## 4) Điều kiện bắt buộc để realtime hoạt động

- Backend:
    - `BROADCAST_CONNECTION` cấu hình đúng.
    - Queue worker chạy.
    - Scheduler chạy command outbox.
- Frontend:
    - Echo được init trong `resources/js/app.ts` qua hàm `setupEcho` (định nghĩa tại `resources/js/lib/echo.ts`).
    - user đã auth.

## 5) Security rules

- Mặc định strict campus isolation theo `event.campus_id`.
- Ngoại lệ cho global/system-security theo allowlist config.
- Channel auth kiểm tra user + campus trong `routes/channels.php`.

## 6) Chính sách unresolved recipient

Nếu target không resolve được về `user_id`:

- skip delivery
- ghi audit log
- tăng metric `notification_recipient_unresolved_total`
- không fallback legacy

## 7) Quick test

1. Tạo event có `recipient_targets` hợp lệ.
2. Chạy:
    - `php artisan notifications:process-outbox --limit=100`
3. Mở UI đã login đúng user/campus.
4. Kiểm tra toast + list noti cập nhật realtime.
