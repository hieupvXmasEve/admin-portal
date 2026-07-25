---
title: Lecturer API
description: Canonical navigation for the lecturer portal API.
audience:
    - Lecturer portal developers
    - Backend maintainers
status: current
owner: Platform Team
last_verified: 2026-07-25
scope: lecturer-api-navigation
source_of_truth:
    - routes/api.php
    - routes/api/v1/lecturer.php
    - app/Modules/Identity/routes/api.php
---

# Lecturer API

Base path: `/api/v1/lecturer`

Protected routes require `auth:sanctum`, `api.actor:lecturer`,
`lecturer.api.auth`, and API logging. Controllers further verify ownership of
course offerings, sessions, students, attendance, and assessment records.

## Documentation map

| Area                                             | Page                          |
| ------------------------------------------------ | ----------------------------- |
| Login, token rotation, profile                   | [Authentication](auth.md)     |
| Assessment structure, grades, reports, gradebook | [Assessments](assessments.md) |
| Attendance                                       | [Attendance](attendance.md)   |
| Notifications                                    | [Notifications](notifications.md) |
| Dashboard, course offerings, rosters, students   | [Courses](courses.md)         |
| Form availability                                | [Forms](forms.md)             |
| Timetable and session mutation                   | [Timetable](timetable.md)     |

The standard JSON envelope is documented in
[`docs/api/README.md`](../README.md). Assessment report downloads are file
responses and some report endpoints retain their established response shape.

## Dashboard endpoints

The following protected reads are registered:

- `GET /dashboard`;
- `GET /dashboard/teaching-summary`;
- `GET /dashboard/attendance-overview`;
- `GET /dashboard/student-alerts`;
- `GET /dashboard/upcoming-sessions`;
- `GET /dashboard/recent-activities`.

Their query validation and response fields are owned by the Academic Delivery
`app/Modules/Academic/Delivery/Http/Api/Lecturer/DashboardController.php`,
`DashboardFilterRequest`,
`LecturerDashboardService`, and `DashboardResource`.
