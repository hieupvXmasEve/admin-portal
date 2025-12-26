# Hướng dẫn Kết nối Realtime cho Student Portal (Nuxt SPA)

Vì Student Portal là một ứng dụng Nuxt riêng biệt (Standalone SPA), việc kết nối với hệ thống Realtime của Backend (Laravel) cần lưu ý về cấu hình CORS và Authentication (Sanctum/JWT).

## 1. Cài đặt thư viện (Client-side)

Trong dự án Nuxt của bạn, hãy cài đặt các thư viện cần thiết:

```bash
pnpm add laravel-echo ably
```

## 2. Cấu hình Biến môi trường (`.env` của Nuxt)

Bạn cần cung cấp URL của Backend Laravel để Echo có thể thực hiện việc "Authorization" cho các Private Channel.

```env
# URL của Backend Laravel (Swinx Admin)
NUXT_PUBLIC_BACKEND_URL=https://api.swinx.edu.vn

# Cấu hình Ably
NUXT_PUBLIC_BROADCASTER=ably
NUXT_PUBLIC_ABLY_KEY=your-ably-key-here
```

## 3. Tạo Plugin Echo cho Nuxt (`plugins/echo.client.ts`)

Trong Nuxt, chúng ta khởi tạo Echo như một plugin để sử dụng toàn cục.

```ts
import Echo from 'laravel-echo';
import * as Ably from 'ably';

export default defineNuxtPlugin(async (nuxtApp) => {
    const config = useRuntimeConfig();
    const token = useCookie('auth_token'); // Giả sử bạn lưu token trong cookie

    if (!config.public.NUXT_PUBLIC_ABLY_KEY) return;

    // Cần đưa Ably vào global window cho Echo
    window.Ably = Ably;

    const echo = new Echo({
        broadcaster: 'ably',
        key: config.public.NUXT_PUBLIC_ABLY_KEY,
        // URL để Echo thực hiện phân quyền (Authorization) cho Private Channel
        authEndpoint: `${config.public.NUXT_PUBLIC_BACKEND_URL}/broadcasting/auth`,
        auth: {
            headers: {
                Authorization: `Bearer ${token.value}`,
                Accept: 'application/json',
            },
        },
    });

    return {
        provide: {
            echo,
        },
    };
});
```

## 4. Sử dụng trong Component/Composable (`composables/useRealtime.ts`)

```ts
export const useRealtimeNotification = (userId: number) => {
    const { $echo } = useNuxtApp();
    const notifications = ref([]);

    onMounted(() => {
        if (!$echo) return;

        $echo.private(`notifications.${userId}`).listen('.NotificationCreated', (data: any) => {
            notifications.value.unshift(data);
            // Hiển thị notification (ví dụ dùng thư viện ui của Nuxt)
            // pushNotification(data.title, data.message);
        });
    });

    onUnmounted(() => {
        if ($echo) {
            $echo.leave(`notifications.${userId}`);
        }
    });

    return { notifications };
};
```

## 5. Cấu hình Backend (Laravel) cần lưu ý

Để ứng dụng Nuxt có thể kết nối được, bạn cần đảm bảo các mục sau trên Backend (Swinx Admin):

1.  **CORS**: File `config/cors.php` phải cho phép nguồn (Origin) của ứng dụng Nuxt.
    ```php
    'allowed_origins' => [env('STUDENT_PORTAL_URL', 'http://localhost:3000')],
    'supports_credentials' => true,
    ```
2.  **Broadcasting Routes**: Đảm bảo route `/broadcasting/auth` đã được đăng ký và sử dụng middleware phù hợp (thông thường là `auth:sanctum` hoặc middleware bạn dùng cho API).
    - Kiểm tra trong `bootstrap/app.php` phần `channels` đã được đăng ký.
    - Kiểm tra `routes/channels.php` đã định nghĩa logic authorization.

## 6. Luồng kết nối khi dùng SPA

1.  Nuxt bắt đầu kết nối tới hạ tầng Ably bằng API Key.
2.  Vì là `PrivateChannel`, Echo sẽ gửi một request POST tới `Backend_URL/broadcasting/auth` kèm theo Token của người dùng.
3.  Backend kiểm tra Token, nếu hợp lệ sẽ trả về mã bí mật để Echo hoàn tất việc đăng ký lắng nghe kênh đó trên Ably.
4.  Khi Backend phát tin, Ably sẽ chuyển tin nhắn về đúng Client đã được xác thực thành công.
