# Realtime Notification Setup Guide (Ably)

This guide documents the implementation of realtime notifications using **Ably** and **Laravel Broadcasting**, following the strict rules defined in `docs/rules/realtime_notification.md`.

## 1. Backend Configuration (Laravel)

### Dependencies

Installed the Ably PHP SDK:

```bash
composer require ably/ably-php
```

### Environment Variables (.env)

Configure your Ably API key in `.env`:

```env
BROADCAST_CONNECTION=ably
ABLY_KEY=your-ably-api-key
```

### Broadcasting Config

The project uses the native `ably` driver in `config/broadcasting.php`.

```php
'ably' => [
    'driver' => 'ably',
    'key' => env('ABLY_KEY'),
],
```

### Event Implementation

A generic event `App\Events\NotificationBroadcast` was created. It implements `ShouldBroadcast` and targets the `notifications.{userId}` private channel.

### Automatic Broadcasting

The `Notification` model is configured to automatically trigger a broadcast when a new record is created with the `broadcast` channel:

```php
protected static function booted()
{
    static::created(function ($notification) {
        if (in_array('broadcast', $notification->channels ?? [])) {
            broadcast(new \App\Events\NotificationBroadcast($notification));
        }
    });
}
```

## 2. Frontend Configuration (Vue 3 + Inertia)

### Dependencies

Installed Laravel Echo and Ably JS SDK:

```bash
npm install laravel-echo ably
```

### Echo Service Abstraction (`resources/js/lib/echo.ts`)

As per Rule 4.2, Echo initialization is abstracted. It uses dynamic imports to ensure vendor SDKs are only loaded when needed and are interchangeable.

### Usage in Components

Use the `useRealtimeNotifications` composable in your layouts or pages:

```vue
<script setup lang="ts">
import { useRealtimeNotifications } from '@/composables/useRealtimeNotifications';
import { usePage } from '@inertiajs/vue3';

const { props } = usePage();
const userId = props.auth.user.id;

// Automatically connects and listens
useRealtimeNotifications(userId);
</script>
```

## 3. Security & Authorization

### Channel Authorization (`routes/channels.php`)

Private channels are authorized to ensure users only listen to their own notifications:

```php
Broadcast::channel('notifications.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

## 4. Key Principles Followed

1. **Vendor Agnostic**: The frontend logic only knows about `Laravel Echo`. Switching from Ably to Pusher or Reverb only requires updating `.env` and possibly one small update in the `echo.ts` factory (Infrastructure layer).
2. **Domain Driven**: Realtime events are tied to the `Notification` model lifecycle.
3. **Queue Based**: Broadcasting is handled via Laravel's queue system for performance.
4. **Strict Naming**: Private channels follow the `{resource}.{id}` convention.
