# Hướng dẫn Sử dụng Realtime Notification (Ably)

Tài liệu này hướng dẫn cách sử dụng hệ thống thông báo thời gian thực đã được thiết lập trong dự án Swinx.

## 1. Luồng hoạt động (Data Flow)

1.  **Backend**: Một hành động xảy ra (Ví dụ: Giảng viên nhập điểm).
2.  **Notification Service**: Tạo một bản ghi vào bảng `notifications` với channel là `['database', 'broadcast']`.
3.  **Model Observer**: Model `Notification` nhận thấy có channel `broadcast`, tự động kích hoạt Event `NotificationBroadcast`.
4.  **Ably**: Laravel gửi thông tin thông báo qua Ably (Server-side).
5.  **Frontend**: Laravel Echo (Client-side) đang lắng nghe trên kênh `notifications.{userId}` sẽ nhận được dữ liệu.
6.  **UI**: Component hiển thị Toast (thông báo popup) và cập nhật danh sách thông báo mà không cần reload trang.

---

## 2. Cách tạo Thông báo từ Backend

Để gửi một thông báo có tính năng realtime, bạn chỉ cần sử dụng Model `Notification` và đảm bảo có giá trị `broadcast` trong mảng `channels`.

### Ví dụ 1: Gửi thông báo khi có thông báo học vụ (Academic Hold)

```php
use App\Models\Notification;
use App\Enums\NotificationCategory;

Notification::create([
    'type' => 'academic_hold',
    'notifiable_type' => Student::class,
    'notifiable_id' => $studentId, // ID của sinh viên nhận thông báo
    'category' => NotificationCategory::ACADEMIC,
    'title' => 'Cảnh báo học vụ!',
    'message' => 'Bạn có một thông báo mới về tình trạng học tập. Vui lòng kiểm tra.',
    'data' => [
        'action_url' => '/student/profile', // Đường dẫn khi click vào thông báo
        'action_text' => 'Xem chi tiết',
    ],
    'channels' => ['database', 'broadcast'], // QUAN TRỌNG: Phải có 'broadcast' để có realtime
    'is_important' => true,
]);
```

---

## 3. Cách lắng nghe thông báo ở Frontend (Vue 3)

Chúng ta sử dụng Composable `useRealtimeNotifications` để việc tích hợp trở nên đơn giản nhất.

### Ví dụ 2: Tích hợp vào Layout chính (Ví dụ: `AppLayout.vue`)

```vue
<script setup lang="ts">
import { useRealtimeNotifications } from '@/composables/useRealtimeNotifications';
import { usePage } from '@inertiajs/vue3';
import { Toaster } from '@/components/ui/sonner'; // Đảm bảo đã có Toaster để hiển thị popup

const page = usePage();
// Lấy ID người dùng hiện tại từ bộ nhớ dùng chung của Inertia
const userId = page.props.auth.user.id;

// Kích hoạt lắng nghe.
// Composable này sẽ tự động:
// 1. Kết nối tới Ably qua Echo.
// 2. Lắng nghe channel riêng của User.
// 3. Hiển thị Toast (popup) khi có thông báo mới.
const { notifications } = useRealtimeNotifications(userId);

// Biến `notifications` sẽ chứa danh sách các thông báo nhận được trong phiên làm việc hiện tại.
</script>

<template>
    <div>
        <!-- Render app content -->
        <slot />

        <!-- Component hiển thị popup thông báo -->
        <Toaster />
    </div>
</template>
```

---

## 4. Cấu hình Quan trọng trong `.env`

Để hệ thống chạy được, bạn phải cung cấp API Key từ Ably:

```env
# 1. Driver cho Laravel (Backend)
BROADCAST_CONNECTION=ably
ABLY_KEY=abc:xyz... (Lấy từ dashboard Ably)

# 2. Key cho Vite (Frontend) - Tự động map từ ABLY_KEY
VITE_BROADCASTER=${BROADCAST_CONNECTION}
VITE_BROADCAST_KEY="${ABLY_KEY}"
```

## 5. Các lưu ý quan trọng (Rules)

1.  **Phân quyền**: Các channel thông báo là `PrivateChannel`, nghĩa là chỉ đúng User đó mới có quyền nghe thông báo của mình. Việc này được cấu hình tại `routes/channels.php`.
2.  **Queue**: Laravel sẽ gửi thông báo qua Queue để không làm chậm request của người dùng. Hãy đảm bảo lệnh `php artisan queue:work` đang chạy.
3.  **Thay thế**: Sau này nếu muốn đổi sang Server riêng (Laravel Reverb) thay vì Ably, bạn **KHÔNG CẦN** sửa code, chỉ cần đổi `BROADCAST_CONNECTION=reverb` trong `.env`.
