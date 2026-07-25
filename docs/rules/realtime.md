---
title: Realtime and Notification Rules
status: active
owner: Platform Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - app/Events
  - app/Modules/Notification
  - resources/js
  - routes/channels.php
---

# Realtime and Notification Rules

Laravel broadcasting is the application boundary. Ably, Pusher, Reverb, and
other providers are transport choices; business behavior must not depend on a
provider SDK.

## Backend broadcasting

- Generic broadcast events use the existing `app/Events` owner; domain
  notification behavior remains in `app/Modules/Notification`.
- Broadcast events implement `ShouldBroadcast` and should be queued.
- Name channels by resource and scope, for example `users.{userId}`. Do not put
  vendor or UI names in channel contracts.
- Authorize private channels in `routes/channels.php`; authorization remains
  backend-owned.
- Keep payloads explicit, minimal, campus-scoped, and safe for the authorized
  recipient.
- Provider changes should be configuration-only.

## Frontend subscriptions

- Subscribe through Laravel Echo.
- Do not import Ably, Pusher, or another vendor SDK in feature code.
- Centralize Echo initialization and use private channels for user-scoped data.
- Unsubscribe or stop listeners when the owning component lifecycle ends.

## Notification V2 delivery

- Persist notification events to `notification_event_outbox` before processing.
- Resolve recipients to `recipient_user_id` before persistence.
- Preserve the same `campus_id` across event, message, and delivery records.
- Dispatch through `notifications:process-outbox`; never bypass the outbox by
  writing delivery records directly.
- New behavior uses the V2 notification tables; do not add a legacy
  `notifications` backfill.

## Configuration and migration safety

- Select the backend through `BROADCAST_CONNECTION`.
- Expose normalized frontend configuration such as `VITE_BROADCASTER`,
  `VITE_BROADCAST_KEY`, and `VITE_SOCKET_HOST`.
- Do not reference vendor-specific environment variable names in feature code;
  map them in configuration.
- A move between hosted and self-hosted transports must not require rewriting
  backend events or frontend subscriptions.
