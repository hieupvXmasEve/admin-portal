---
paths: "**/*.{php,vue,js,ts}"
---

# Realtime Notification Rules

## 1. Core Principles
- **Laravel Broadcasting**: The SINGLE source of truth.
- **Transport Independence**: Ably, Pusher, Reverb are just transport layers. Business logic MUST NOT depend on them.
- **Switchable**: Changing providers should only require `.env` changes.

## 2. Backend Rules (Laravel)
- **Events**: Must implement `ShouldBroadcast`.
- **Location**: `app/Events`.
- **Channel Naming**: `{resource}.{scope_identifier}` (e.g., `users.{userId}`).
    - ❌ No vendor prefixes.
    - ❌ No UI-based naming.
- **Authorization**: `routes/channels.php`. Must be backend-only logic.
- **Queues**: Broadcast events SHOULD be queued.

## 3. Frontend Rules (Vue 3 / Inertia)
- **Laravel Echo**: MANDATORY.
- **Forbidden**: Importing vendor SDKs (Ably, Pusher) directly.
- **Initialization**: Must be abstracted (e.g., `createEcho()` factory).
- **Syntax**: `Echo.private('...').listen('EventName', ...)`

## 4. Environment Variables
- **Broadcaster Selection**: `BROADCAST_CONNECTION=ably|reverb|log`
- **Frontend Config**: `VITE_BROADCASTER`, `VITE_BROADCAST_KEY`, `VITE_SOCKET_HOST`.
- **Forbidden**: Vendor-specific keys like `VITE_ABLY_KEY` in code (map them in config).

## 5. Migration Safety
- **Rule**: Code must support migrating from SaaS (Ably) to Self-Hosted (Reverb) without rewriting Backend/Frontend logic.
