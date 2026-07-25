---
title: Lecturer attendance API
description: Attendance session reads, marking, analytics, export, and record generation.
audience:
    - Lecturer portal developers
status: current
owner: Academic Team
last_verified: 2026-07-25
scope: lecturer-attendance-api
source_of_truth:
    - routes/api/v1/lecturer.php
    - app/Modules/Academic/Delivery/Http/Api/Lecturer/AttendanceController.php
    - app/Modules/Academic/Http/Requests/Delivery
---

# Lecturer attendance API

Base path: `/api/v1/lecturer/attendance`

All routes require the protected lecturer middleware stack and are scoped to
the authenticated lecturer's teaching assignments.

## Endpoints

| Method | Path                                  | Input                                                                                                                              |
| ------ | ------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `GET`  | `/`                                   | Optional `course_offering_id`, `semester_id`, session `status`, `attendance_status`, `date_from`, `date_to`, `per_page` (5 to 50). |
| `GET`  | `/summary`                            | Summary filters in `LecturerAttendanceSummaryRequest`.                                                                             |
| `GET`  | `/alerts`                             | Alert filters in `LecturerAttendanceAlertsRequest`.                                                                                |
| `GET`  | `/sessions-requiring-attention`       | Filters in `LecturerAttendanceRequest`.                                                                                            |
| `GET`  | `/sessions/{session}`                 | None.                                                                                                                              |
| `POST` | `/sessions/{session}/mark`            | `attendance_data`, 1 to 200 records.                                                                                               |
| `POST` | `/bulk-mark`                          | `sessions`, 1 to 10 session payloads.                                                                                              |
| `GET`  | `/courses/{courseOffering}/analytics` | Analytics filters in `CourseAttendanceAnalyticsRequest`.                                                                           |
| `GET`  | `/courses/{courseOffering}/export`    | `format` and export filters in `ExportCourseAttendanceRequest`.                                                                    |
| `POST` | `/{session}/generate-attendance`      | None.                                                                                                                              |

Each item in `attendance_data` requires `student_id` and `status`. Status
accepts `present`, `absent`, `late`, or `excused`; optional fields include
`check_in_time`, `minutes_late`, `participation_score`, and `notes`.

Bulk marking uses:

```json
{
    "sessions": [
        {
            "session_id": 123,
            "attendance_data": [
                {
                    "student_id": 456,
                    "status": "present"
                }
            ]
        }
    ]
}
```

The export endpoint returns a JSON success payload produced by the attendance
query; it is not one of the assessment file-download routes.

The generation path is exactly
`/attendance/{session}/generate-attendance`. Former docs that used
`/sessions/{session}/generate` described no registered route.
