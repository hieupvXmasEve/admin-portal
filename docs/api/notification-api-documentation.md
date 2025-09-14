# Notification System API Documentation

This document provides comprehensive API documentation for the Notification System, including REST endpoints, WebSocket events (Laravel Reverb), authentication details, and error handling.

## Table of Contents

- Overview
- Authentication
- REST Endpoints
- WebSocket Events
- Error Handling
- Examples

## Overview

The Notification System supports multi-channel delivery and management:

- Fetch, read, and delete notifications
- Admin-triggered notifications and analytics
- User notification preferences by category/channel
- Web Push subscription management
- Real-time delivery via Laravel Reverb (WebSockets)

### Base URL
`/api/v1/notifications`

### Content Type
All API requests should use `application/json`.

### Common Response Envelope
```json
{
  "success": true,
  "data": {},
  "message": "...",
  "timestamp": "2024-01-15T10:30:00Z",
  "meta": {}
}
```

## Authentication

- All endpoints require authentication via Laravel Sanctum tokens.
- Include the token in the Authorization header: `Authorization: Bearer {token}`.

## REST Endpoints

### Notifications

- GET `/api/v1/notifications`
  - Query params: `category` (academic|system|finance|personal), `read` (true|false), `date_from`, `date_to`, pagination params (e.g., `page`, `per_page`).
  - Returns paginated notifications for the authenticated user.

- GET `/api/v1/notifications/unread-count`
  - Optional query param: `category` as above.
  - Returns `{ unread_count, category }`.

- GET `/api/v1/notifications/{id}`
  - Returns a notification with delivery logs; auto-marks as read if unread.

- POST `/api/v1/notifications/{id}/mark-as-read`
  - Marks a single notification as read.

- POST `/api/v1/notifications/mark-all-as-read`
  - Body (optional): `{ "category": "academic|system|finance|personal" }`
  - Marks all (or by category) as read.

- DELETE `/api/v1/notifications/{id}`
  - Deletes a single notification for the user.

- POST `/api/v1/notifications` (admin only)
  - Requires `can:send-notifications` permission.
  - Body example:
    ```json
    {
      "recipients": [{"user_id": 1}],
      "type": "manual",
      "category": "system",
      "title": "System Update",
      "message": "We will perform maintenance tonight.",
      "data": {"url": "/status"},
      "channels": ["database", "broadcast", "webpush"],
      "is_important": true,
      "expires_at": "2025-01-31T23:59:59Z"
    }
    ```

### Analytics (admin)

All routes below require `can:view-notification-analytics`.

- GET `/api/v1/notifications/analytics/overview` – summarized analytics.
- GET `/api/v1/notifications/analytics/delivery-stats`
- GET `/api/v1/notifications/analytics/channel-performance`
- GET `/api/v1/notifications/analytics/category-breakdown`
- GET `/api/v1/notifications/analytics/read-rates`
- GET `/api/v1/notifications/analytics/failed-deliveries`
- GET `/api/v1/notifications/analytics/trends`
- GET `/api/v1/notifications/analytics/real-time-metrics`
- GET `/api/v1/notifications/analytics/report`
- POST `/api/v1/notifications/analytics/retry-delivery` – body: `{ "notification_id": "...", "channel": "database|broadcast|webpush" }`
- POST `/api/v1/notifications/analytics/clear-cache`

Common filters supported where noted: `date_from`, `date_to`, `channel` (database|broadcast|webpush), `category` (academic|system|finance|personal), `status` (pending|delivered|failed|expired).

### Preferences

- GET `/api/v1/notifications/preferences`
  - Returns the user’s category-level preferences including enabled state and channels.

- PUT `/api/v1/notifications/preferences`
  - Body: `{ "preferences": [{ "category": "system", "enabled": true, "channels": ["database", "broadcast"] }] }`

- GET `/api/v1/notifications/preferences/defaults`
  - Optional query param: `category`.

- POST `/api/v1/notifications/preferences/reset-to-defaults`
  - Optional body: `{ "category": "academic|system|finance|personal" }`

- POST `/api/v1/notifications/preferences/toggle-category`
  - Body: `{ "category": "...", "enabled": true|false }`

- GET `/api/v1/notifications/preferences/summary`
  - Returns enabled/disabled counts by category.

### Web Push

- GET `/api/v1/notifications/web-push/check-support`
  - Returns `{ supported: boolean, vapid_public_key: string|null }`.

- POST `/api/v1/notifications/web-push/subscribe`
  - Body:
    ```json
    {
      "endpoint": "https://fcm.googleapis.com/fcm/send/...",
      "keys": {"p256dh": "...", "auth": "..."},
      "user_agent": "..."
    }
    ```
  - Creates or updates an active subscription.

- POST `/api/v1/notifications/web-push/unsubscribe`
  - Body: `{ "endpoint": "..." }`

- GET `/api/v1/notifications/web-push/subscriptions`
  - Lists active subscriptions; includes meta totals.

- DELETE `/api/v1/notifications/web-push/subscriptions/{id}`
  - Revokes (deactivates) a specific subscription.

## WebSocket Events

### Broadcaster
- Driver: `reverb` (Laravel Reverb WebSocket server)
- Auth endpoint: `/broadcasting/auth`
- Private channels require authenticated Sanctum token in Echo auth headers.

### Channels
- `private-notifications.{userId}` – Individual user notifications (configured as `notifications.{userId}` in `routes/channels.php`)
- Additional channels (if used by admins or dashboards): `campus.{campusId}`, `role.{roleId}`, `system-announcements`, `admin-notifications`, `notification-analytics`.

### Event: `notification.sent`
- Broadcast on: `private notifications.{userId}`
- Payload example:
```json
{
  "id": "uuid",
  "type": "App\\Models\\Notification",
  "category": "system",
  "title": "System Update",
  "message": "We will perform maintenance tonight.",
  "data": {"url": "/status"},
  "is_important": true,
  "expires_at": "2025-01-31T23:59:59Z",
  "created_at": "2025-01-15T10:30:00Z",
  "read_at": null,
  "channels": ["database", "broadcast"],
  "category_label": "System",
  "age": "1s ago",
  "expires_in": "Expires in 2 days"
}
```

### Echo Client Example (Vue / TS)
```ts
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT),
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
  authEndpoint: '/broadcasting/auth',
  auth: { headers: { Authorization: `Bearer ${token}` } },
})

echo.private(`notifications.${userId}`)
  .listen('.notification.sent', (payload) => {
    console.log('New notification:', payload)
  })
```

## Error Handling

All errors share the envelope:
```json
{
  "success": false,
  "message": "...",
  "errors": [ { "code": "...", "field": null, "detail": null } ],
  "timestamp": "..."
}
```

Common `errors[*].code` values:
- `VALIDATION_ERROR` (422)
- `AUTHENTICATION_ERROR` (401)
- `AUTHORIZATION_ERROR` (403)
- `NOT_FOUND` (404)
- `BUSINESS_LOGIC_ERROR` (422)
- `SERVER_ERROR` (500)
- `RATE_LIMIT` (429)

## Examples

### Fetch Notifications
```http
GET /api/v1/notifications?category=system&read=false&page=1&per_page=15
Authorization: Bearer {token}
```

### Mark All As Read (System Category)
```http
POST /api/v1/notifications/mark-all-as-read
Authorization: Bearer {token}
Content-Type: application/json

{ "category": "system" }
```

### Update Preferences
```http
PUT /api/v1/notifications/preferences
Authorization: Bearer {token}
Content-Type: application/json

{
  "preferences": [
    { "category": "system", "enabled": true, "channels": ["database", "broadcast"] },
    { "category": "academic", "enabled": true, "channels": ["database", "webpush"] }
  ]
}
```

