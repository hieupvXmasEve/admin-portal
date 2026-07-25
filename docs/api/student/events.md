---
title: Student events API
description: Campus event discovery, registration, and participation history.
audience:
    - Student portal developers
status: current
owner: Engagement Team
last_verified: 2026-07-25
scope: student-events-api
source_of_truth:
    - app/Modules/Engagement/routes/api.php
    - app/Modules/Engagement/Http/Api/Student/EventController.php
    - app/Http/Resources/Api/V1/Student/EventResource.php
    - app/Http/Resources/Api/V1/Student/EventParticipantResource.php
---

# Student events API

Base path: `/api/v1/student/events`

All routes require an authenticated student or authorized parent proxy.
Results are scoped to the selected student's campus.

## Endpoints

| Method   | Path                 | Input                                                                                              |
| -------- | -------------------- | -------------------------------------------------------------------------------------------------- |
| `GET`    | `/`                  | Optional `search`, `status` (default `published`), `time_filter`, `page`, `per_page` (maximum 50). |
| `GET`    | `/{event}`           | None.                                                                                              |
| `POST`   | `/{event}/register`  | None.                                                                                              |
| `DELETE` | `/{event}/register`  | None.                                                                                              |
| `GET`    | `/my/participations` | Optional `status`, `date_from`, `date_to`, `page`, `per_page` (maximum 50).                        |
| `GET`    | `/my/{event}`        | None.                                                                                              |

`time_filter` is interpreted by the Engagement event service for upcoming,
ongoing, or past discovery. Participation status values are owned by the
event-participation model/service.

## Response notes

- The discovery response puts `events` and `pagination` inside `data`; its
  pagination object uses `current_page`, `per_page`, `total`, `last_page`,
  `from`, and `to`.
- The participation list similarly returns `participations` and `pagination`
  inside `data`.
- Resource field names are owned by `EventResource` and
  `EventParticipantResource`.

Draft and cross-campus events are returned as `404`. Registration lifecycle
conflicts return either a validation error or a `400` compatibility error,
depending on the owning service exception.
