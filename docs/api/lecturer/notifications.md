---
title: Lecturer notifications API
description: Lecturer-scoped notification inbox and read-state actions.
audience:
    - Lecturer portal developers
    - Backend maintainers
status: current
owner: Notification Team
last_verified: 2026-07-25
scope: lecturer-notifications-api
source_of_truth:
    - routes/api/v1/lecturer.php
    - app/Modules/Notification/Http/Api/Lecturer/NotificationController.php
    - app/Modules/Notification/Http/Requests/Lecturer/NotificationFilterRequest.php
    - app/Modules/Notification/Http/Resources/StudentNotificationResource.php
---

# Lecturer notifications API

All endpoints use `/api/v1/lecturer`, require the protected lecturer
middleware stack, and scope messages by the authenticated lecturer's Identity
account and campus.

| Method | Path | Input |
| ------ | ---- | ----- |
| `GET` | `/notifications` | Optional `is_read`, `date_from`, `date_to`, `page`, and `per_page`. |
| `POST` | `/notifications/{notification}/mark-read` | Notification ID in the path. |
| `POST` | `/notifications/mark-all-read` | No body. |

The list response uses the standard `success`, `data`, `meta`, and `message`
envelope. Pagination metadata includes `page`, `per_page`, `total`,
`total_pages`, and `unread_count`. A notification cannot be read by another
lecturer, even when its numeric ID is known.

Each item contains `id`, `title`, `message`, `category`, `type_key`,
`event_name`, `is_read`, `is_important`, `time_ago`, `timestamps`, `data`, and
`ui`. The mark-read response returns the current `unread_count`; the
mark-all-read response returns `updated_count` and `unread_count`. An unknown
or already-read notification returns the standard business-logic error.
