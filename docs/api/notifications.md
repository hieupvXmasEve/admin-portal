---
title: Student notifications API
description: Student notification inbox actions and event preference routes.
audience:
    - Student portal developers
    - Notification maintainers
status: current
owner: Notification Team
last_verified: 2026-07-25
scope: student-notifications-api
source_of_truth:
    - routes/api/v1/student.php
    - app/Modules/Notification/Http/Api/Student/NotificationController.php
    - app/Modules/Notification/Http/Api/Student/NotificationPreferenceController.php
    - app/Modules/Notification/Http/Resources/StudentNotificationResource.php
---

# Student notifications API

Base path: `/api/v1/student/notifications`

All endpoints require the protected student middleware stack. Inbox mutations
are scoped to the selected student's notification records.

## Inbox endpoints

| Method   | Path                        | Input                                                                                                         |
| -------- | --------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `GET`    | `/`                         | Optional `category`, `type`, `priority`, `is_read`, `date_from`, `date_to`, `page`, `per_page` (maximum 100). |
| `GET`    | `/summary`                  | None.                                                                                                         |
| `POST`   | `/{notification}/mark-read` | None.                                                                                                         |
| `POST`   | `/mark-multiple-read`       | `notification_ids`: 1 to 100 integer ids.                                                                     |
| `POST`   | `/mark-all-read`            | None.                                                                                                         |
| `DELETE` | `/{notification}`           | Archives the notification for the user.                                                                       |

`category` accepts `assessment`, `grade`, `attendance`, `enrollment`,
`academic`, `system`, or `announcement`. `priority` accepts `low`, `medium`,
`high`, or `urgent`.

## Notification resource

Each item produced by `StudentNotificationResource` contains:

- `id`, `title`, `message`, `type_key`, and `event_name`;
- `category` with `key` and display label;
- `is_read`, `is_important`, and `time_ago`;
- `timestamps` for creation, reading, and expiry;
- event-specific `data`;
- `ui` hints with icon, color, and badges.

Clients should treat `data` as event-specific and use the stable top-level
fields for inbox rendering.

The list response uses a standard success envelope whose `data` is the
notification item array. Pagination and `unread_count` are supplied in `meta`.
Mutation responses return updated counts where implemented.

## Event notification preferences

| Method | Path                  |
| ------ | --------------------- |
| `GET`  | `/preferences/events` |
| `PUT`  | `/preferences/events` |

Update input is:

```json
{
    "preferences": {
        "event_type": {
            "enabled": true,
            "frequency": "frequency_key"
        }
    }
}
```

The supported event-type and frequency keys are owned by
`App\Models\UserEmailPreference`. These two established endpoints use a
compatibility envelope without the standard timestamp.

There is no registered generic `/api/v1/notifications` inbox, analytics, web
push, or generic preference surface in the current route inventory.
