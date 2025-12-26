# Realtime Notifications – Code Generation Rules

**(Laravel 12 + Vue 3 + Inertia + Standalone SPA)**

## 1. Purpose of This Rule File

This document defines **strict rules** for generating code related to **realtime notifications** to ensure:

- No vendor lock-in (Ably, Pusher, Reverb, WebSocket are interchangeable)
- Full compatibility with **Laravel 12 Broadcasting**
- Safe migration from **hosted realtime services → self-hosted**
- Shared usage across:
    - Admin Portal (Laravel + Inertia + Vue 3)
    - Student Portal (Standalone Vue 3 SPA)

> ⚠️ Any generated code **MUST follow these rules**.
> Code that violates these rules is considered **invalid**.

## 2. Core Architectural Principles (MANDATORY)

### Rule 2.1 — Laravel Broadcasting Is the Single Source of Truth

- All realtime events **MUST be broadcast using Laravel Broadcasting**
- Events **MUST implement** `ShouldBroadcast` or `ShouldBroadcastNow`
- No direct WebSocket / Ably / Pusher calls are allowed in backend code

✅ Allowed:

```php
class NotificationCreated implements ShouldBroadcast
```

❌ Forbidden:

```php
Ably::publish(...)
Pusher::trigger(...)
```

### Rule 2.2 — Transport Layer Must Be Replaceable

- Ably, Reverb, Pusher, or self-hosted WebSocket are **transport layers only**
- Business logic **MUST NOT depend** on any vendor SDK

➡️ Switching broadcaster **MUST only require `.env` changes**

## 3. Backend Rules (Laravel 12)

### Rule 3.1 — Events

- Events:
    - MUST live in `app/Events`
    - MUST be domain-driven (not UI-driven)
    - MUST NOT reference frontend concerns

Example:

```php
class UserNotificationCreated implements ShouldBroadcast
{
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.' . $this->userId);
    }
}
```

### Rule 3.2 — Channel Naming Convention (STRICT)

Channel names MUST follow this pattern:

```
{resource}.{scope_identifier}
```

Examples:

- `users.{userId}`
- `notifications.{userId}`
- `courses.{courseOfferingId}`

❌ Forbidden:

- Vendor-specific prefixes
- UI-based naming
- Random or hard-coded strings

### Rule 3.3 — Channel Authorization

- All **private / presence channels** MUST be authorized in:
    - `routes/channels.php`

- Authorization logic MUST be backend-only
- Never trust frontend conditions

### Rule 3.4 — Notifications vs Broadcasting

- Laravel Notifications:
    - Used for **storage / email / database**

- Broadcasting:
    - Used **only for realtime UI updates**

- One notification MAY trigger one broadcast event

### Rule 3.5 — Queue Usage

- Broadcasting events SHOULD be queued
- Queue connection MUST be configurable
- Realtime delivery MUST NOT block HTTP requests

## 4. Frontend Rules (Vue 3 / Inertia / SPA)

### Rule 4.1 — Laravel Echo Is Mandatory

- All realtime listeners MUST use **Laravel Echo**
- Do NOT import vendor SDKs (Ably, Pusher, Socket.IO)

❌ Forbidden:

```ts
import * as Ably from 'ably';
import Pusher from 'pusher-js';
```

✅ Required:

```ts
import Echo from 'laravel-echo';
```

### Rule 4.2 — Echo Initialization Must Be Abstracted

Echo MUST be created via a **factory or service file**, not inline.

Example:

```ts
export function createEcho() {
    return new Echo({
        broadcaster: import.meta.env.VITE_BROADCASTER,
        key: import.meta.env.VITE_BROADCAST_KEY,
        host: import.meta.env.VITE_SOCKET_HOST,
    });
}
```

- No hard-coded keys
- No vendor-specific options outside ENV

### Rule 4.3 — Listening Syntax (STRICT)

Frontend MUST use standard Echo APIs only:

```ts
Echo.private(`users.${userId}`).listen('UserNotificationCreated', handler);
```

❌ Forbidden:

- Direct WebSocket handling
- Manual channel subscriptions via vendor SDK

### Rule 4.4 — Authentication Handling

- Auth headers / cookies MUST be handled via Echo config
- Frontend MUST NOT implement authorization logic
- Token-based auth (Sanctum / JWT) MUST be configurable

## 5. Environment Configuration Rules

### Rule 5.1 — ENV-Driven Broadcaster Selection

The broadcaster MUST be switchable via `.env` only.

Examples:

```env
BROADCAST_CONNECTION=ably
BROADCAST_CONNECTION=reverb
```

No code changes are allowed when switching providers.

### Rule 5.2 — Frontend ENV Consistency

Frontend ENV variables MUST be generic:

```env
VITE_BROADCASTER=
VITE_BROADCAST_KEY=
VITE_SOCKET_HOST=
```

❌ Forbidden:

```env
VITE_ABLY_KEY=
VITE_PUSHER_KEY=
```

## 6. Migration Safety Rules (CRITICAL)

Generated code MUST satisfy the following:

- Ably → Self-host migration:
    - No backend code rewrite
    - No frontend listener rewrite
    - Only ENV + infra changes allowed

If migration requires code changes → **rules are violated**

## 7. Multi-Portal Compatibility

Generated code MUST work for:

- Admin Portal (Laravel + Inertia + Vue 3)
- Student Portal (Standalone Vue 3 SPA)

No assumptions about:

- Shared layouts
- Shared auth mechanism
- Same domain

## 8. Forbidden Patterns Summary

The following are **strictly forbidden**:

- Vendor SDK imports in frontend
- Vendor API calls in backend
- Hard-coded channel names
- UI-driven event naming
- Realtime logic mixed with UI components
- Broadcaster-specific logic outside config

## 9. Acceptance Checklist (for AI Validation)

Before outputting code, AI MUST verify:

- ✅ Uses Laravel Broadcasting
- ✅ Uses Laravel Echo
- ✅ No vendor SDK imports
- ✅ ENV-driven broadcaster
- ✅ Channel names follow convention
- ✅ Migration Ably → self-host possible without rewrite

If ANY item fails → code generation MUST STOP.

## 10. Final Principle

> **Realtime is infrastructure, not business logic.**
> Generated code MUST treat realtime delivery as replaceable plumbing.
