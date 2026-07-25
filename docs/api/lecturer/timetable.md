---
title: Lecturer timetable and sessions API
description: Lecturer calendar reads and authorized session mutations.
audience:
    - Lecturer portal developers
status: current
owner: Academic Team
last_verified: 2026-07-25
scope: lecturer-timetable-sessions-api
source_of_truth:
    - routes/api/v1/lecturer.php
    - app/Modules/Academic/Delivery/Http/Api/Lecturer/TimetableController.php
    - app/Http/Requests/Api/V1/Lecturer/TimetableFilterRequest.php
    - app/Http/Requests/Api/V1/Lecturer/ScheduleFilterRequest.php
---

# Lecturer timetable and sessions API

All endpoints use `/api/v1/lecturer` and require the protected lecturer
middleware stack.

## Calendar reads

| Method | Path                  | Input                                                                                           |
| ------ | --------------------- | ----------------------------------------------------------------------------------------------- |
| `GET`  | `/timetable`          | Optional `start_date`, `end_date`, `view`, `course_offering_id`, `status`, `include_cancelled`. |
| `GET`  | `/timetable/schedule` | Optional `start`, `end`, `course_offering_id`, `status`, `include_cancelled`.                   |

`view` accepts `day`, `week`, or `month` and defaults to `week`.
Timetable dates default to the current week. Schedule dates default to the
current month. Session `status` accepts `scheduled`, `completed`, `cancelled`,
or `in_progress`.

There are no registered timetable summary, upcoming-session, or available-room
routes.

## Session mutations

| Method   | Path                    | Input                                 |
| -------- | ----------------------- | ------------------------------------- |
| `POST`   | `/sessions`             | Session creation payload.             |
| `GET`    | `/sessions/{session}`   | None.                                 |
| `PUT`    | `/sessions/{session}`   | Partial session update.               |
| `DELETE` | `/sessions/{session}`   | Optional cancellation `reason`.       |
| `POST`   | `/sessions/bulk-update` | Controller-validated bulk operations. |

Creation requires `course_offering_id`, `session_title`, `session_date`,
`start_time`, and `end_time`. Optional fields include description, type,
delivery mode, room, learning objectives, topics, required materials, and
preparation notes.

Times use `HH:MM`. A new session must last at least 15 minutes and no more than
eight hours. `session_type` accepts `lecture`, `tutorial`, `lab`, `seminar`,
`workshop`, `exam`, or `assessment`; `delivery_mode` accepts `in_person`,
`online`, `hybrid`, or `blended`.

The controller verifies that the lecturer can access the course offering or
session and returns `404` or `422` for inaccessible or conflicting mutations.
