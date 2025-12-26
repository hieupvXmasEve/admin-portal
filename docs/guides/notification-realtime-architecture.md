# Realtime Notifications Architecture

**Laravel 12 + Ably Realtime (Pusher Compatibility) + Nuxt / Vue 3**

## 1. Mục tiêu của kiến trúc

Hệ thống realtime notifications được thiết kế để:

- Cập nhật UI realtime cho:
    - Admin Portal (Laravel + InertiaJS + Vue 3)
    - Student Portal (Nuxt / Vue 3 SPA)

- Không lock-in vendor (Ably → soketi / self-host sau này)
- Tách biệt **business logic** và **realtime transport**
- Có thể scale và migrate mà **không rewrite code FE / BE**

## 2. Tổng quan kiến trúc (High-level)

```
Laravel Backend
(Event + Broadcasting)
        ↓
Laravel Broadcasting (Pusher protocol)
        ↓
Ably Realtime (Pusher Compatibility Mode)
        ↓
Laravel Echo
        ↓
Frontend (Admin + Student portals)
```

**Key idea:**

> Realtime chỉ là _transport layer_, không phải business logic.

## 3. Các thành phần chính

### 3.1 Backend – Laravel 12

#### a. Polymorphic Relationships

Hệ thống sử dụng **Polymorphic Relationships** để gửi thông báo cho nhiều loại đối tượng (`Student`, `User`, `Lecturer`) thông qua một quan hệ duy nhất.

- **Morph Map**: Bắt buộc đăng ký alias trong `AppServiceProvider` để tránh lộ namespace và đảm bảo tính nhất quán của dữ liệu database.
- **HasNotifications Trait**: Mọi model có thể nhận thông báo đều phải sử dụng `App\Traits\HasNotifications` để override mặc định của Laravel và sử dụng custom `Notification` model của dự án.

```php
// app/Models/User.php
class User extends Model {
    use Notifiable, HasNotifications {
        HasNotifications::notifications insteadof Notifiable;
    }
}
```

#### b. Event Broadcasting

- Mọi realtime message **PHẢI đi qua Laravel Broadcasting**
- Event implement `ShouldBroadcast`

```php
// app/Events/NotificationBroadcast.php
class NotificationBroadcast implements ShouldBroadcast
{
    public function broadcastOn()
    {
        // Channel ID là ID của notifiable (Student/User/Lecturer)
        return new PrivateChannel('notifications.' . $this->notification->notifiable_id);
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }
}
```

**Quy tắc bắt buộc**

- Không gọi Ably / WebSocket SDK trực tiếp
- Không push realtime trong controller
- Realtime luôn xuất phát từ Event (thường là từ model hook `created`)

#### c. Channel Authorization

- Private / presence channel được authorize trong `routes/channels.php`

```php
Broadcast::channel('notifications.{id}', function ($user, $id) {
    // Chỉ cho phép user nghe channel của chính mình
    return (int) $user->id === (int) $id;
});
```

#### d. Broadcasting Driver

Backend **luôn dùng Pusher driver**, kể cả khi dùng Ably (thông qua protocol adapter):

```env
BROADCAST_CONNECTION=pusher
```

### 3.2 Realtime Transport – Ably

- Ably được dùng như **WebSocket infrastructure**
- **Pusher Compatibility Mode BẮT BUỘC bật** trong Ably Dashboard.

### 3.3 Frontend – Vue 3 / Inertia

#### a. Laravel Echo Integration (Modern Pattern)

Frontend sử dụng thư viện `@laravel/echo-vue` để quản lý Echo instance dưới dạng Singleton và cung cấp các composables mạnh mẽ.

**Khởi tạo tại `resources/js/lib/echo.ts`:**

```ts
import { configureEcho } from '@laravel/echo-vue';
import Pusher from 'pusher-js';

export function setupEcho() {
    // Tự động detect Ably/Pusher configuration từ ENV
    configureEcho({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_BROADCAST_KEY,
        wsHost: 'realtime-pusher.ably.io',
        wsPort: 443,
        forceTLS: true,
        disableStats: true,
    });
}
```

**Đăng ký trong `app.ts`:**

```ts
import { setupEcho } from './lib/echo';

setupEcho(); // Khởi tạo Echo Singleton
```

#### b. Realtime Composable

Sử dụng hook `useEcho` từ `@laravel/echo-vue` để tự động sub/unsub channel theo lifecycle của component.

```ts
import { useEcho } from '@laravel/echo-vue';

// Trong component hoặc composable
useEcho(`notifications.${userId}`, '.NotificationCreated', (payload) => {
    console.log('Received notification:', payload);
});
```

#### c. Event name rules

| Laravel                  | Frontend                   |
| ------------------------ | -------------------------- |
| Không có `broadcastAs()` | `listen('EventClassName')` |
| Có `broadcastAs()`       | `listen('.custom.event')`  |

⚠️ **Luôn có dấu `.` trước event name khi dùng `broadcastAs()`** (VD: `.NotificationCreated`)

## 4. Debug & Monitoring chuẩn

### 4.1 WebSocket Connection

Thành công khi thấy:

```json
{
    "event": "pusher:connection_established"
}
```

→ chứng tỏ:

- WebSocket OK
- Protocol đúng
- Ably adapter hoạt động

### 4.2 Ably Dev Console

Nếu thấy:

```
You're not attached to any channels yet
```

→ FE **chưa subscribe thành công**, không phải Ably lỗi.

Nguyên nhân thường gặp:

- Subscribe chạy trong SSR
- Sai channel name
- Auth private channel fail
- Sai event name

### 4.3 Test cứu hỏa (public channel)

Luôn test bằng public channel trước:

```php
return new Channel('test');
```

```ts
Echo.channel('test').listen('TestEvent', ...)
```

Nếu public OK → lỗi nằm ở private / auth.

## 5. Anti-patterns (DEV MỚI PHẢI TRÁNH)

❌ Import Ably SDK trong FE
❌ Push realtime trực tiếp trong controller
❌ Hard-code channel name
❌ Gắn realtime logic vào UI component
❌ Viết code phụ thuộc vendor

## 6. Chiến lược dài hạn (đã được chuẩn bị)

### Hiện tại

- Ably Realtime (hosted, ổn định, nhanh setup)

### Tương lai

- Migrate sang **soketi (self-host)**:
    - Không rewrite FE
    - Không rewrite Event
    - Chỉ đổi `.env` + infra

👉 Kiến trúc hiện tại **đã sẵn sàng migrate**

## 7. Kết luận cho dev mới

> Nếu bạn hiểu 3 điều sau, bạn sẽ làm realtime đúng:
>
> 1. **Realtime = Event + Broadcasting**
> 2. **Echo là cổng duy nhất ở frontend**
> 3. **Vendor chỉ là transport, có thể thay**
