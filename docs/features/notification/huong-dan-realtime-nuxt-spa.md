# Hướng dẫn Realtime Notification cho Nuxt SPA

Tài liệu này dành cho portal Nuxt tách riêng backend Laravel.

## 1) Cài thư viện

```bash
pnpm add laravel-echo pusher-js
```

> Dùng `pusher-js` vì cả Reverb/Pusher/Ably compatibility đều nói protocol Pusher.

## 2) Biến môi trường Nuxt

```env
NUXT_PUBLIC_BACKEND_URL=https://api.your-domain.com
NUXT_PUBLIC_BROADCASTER=ably
NUXT_PUBLIC_BROADCAST_KEY=xxxx
NUXT_PUBLIC_SOCKET_HOST=
NUXT_PUBLIC_SOCKET_PORT=
```

## 3) Plugin Echo (`plugins/echo.client.ts`)

```ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export default defineNuxtPlugin(() => {
    const config = useRuntimeConfig();
    const token = useCookie('auth_token');

    (window as any).Pusher = Pusher;

    const echo = new Echo({
        broadcaster: config.public.NUXT_PUBLIC_BROADCASTER === 'ably' ? 'pusher' : config.public.NUXT_PUBLIC_BROADCASTER,
        key: String(config.public.NUXT_PUBLIC_BROADCAST_KEY || ''),
        forceTLS: true,
        authEndpoint: `${config.public.NUXT_PUBLIC_BACKEND_URL}/broadcasting/auth`,
        auth: {
            headers: {
                Authorization: `Bearer ${token.value || ''}`,
                Accept: 'application/json',
            },
        },
    });

    return { provide: { echo } };
});
```

## 4) Subscribe channel trong Nuxt composable

```ts
export const useRealtimeNotification = (userId: number, campusId: number | null) => {
    const { $echo } = useNuxtApp();

    onMounted(() => {
        if (!$echo) return;

        if (campusId) {
            $echo.private(`notify.${campusId}.${userId}`).listen('.NotificationCreated', (payload: any) => {
                console.log('notification', payload);
            });
        }

        $echo.private(`notifications.${userId}`).listen('.NotificationCreated', (payload: any) => {
            console.log('legacy-notification', payload);
        });
    });

    onUnmounted(() => {
        if (!$echo) return;
        if (campusId) {
            $echo.leave(`notify.${campusId}.${userId}`);
        }
        $echo.leave(`notifications.${userId}`);
    });
};
```

## 5) Backend requirements

- `routes/channels.php` phải authorize đúng guard + campus.
- API auth cho `/broadcasting/auth` phải chấp nhận token của SPA.
- CORS phải allow origin của Nuxt app.

## 6) Lỗi thường gặp

- `403 broadcasting/auth`: token/guard sai.
- connect được nhưng không có event: subscribe sai channel key.
- chỉ nhận legacy channel: campusId null hoặc auth campus mismatch.
