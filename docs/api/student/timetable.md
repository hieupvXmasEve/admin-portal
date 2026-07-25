---
title: Student timetable API
description: Current student timetable filters and response ownership.
audience:
    - Student portal developers
status: current
owner: Academic Team
last_verified: 2026-07-25
scope: student-timetable-api
source_of_truth:
    - routes/api/v1/student.php
    - app/Http/Controllers/Api/V1/Student/TimetableController.php
    - app/Http/Requests/Api/V1/Student/TimetableFilterRequest.php
    - app/Services/V1/Student/TimetableService.php
---

# Student timetable API

`GET /api/v1/student/timetable`

The route requires the protected student middleware stack. It returns the
selected student's timetable in the standard success envelope.

## Filters

All filters are optional:

- `semester_id`;
- `week_start` and `week_end`;
- `day_of_week`: Monday through Sunday, lowercase;
- `session_type`;
- `lecturer_name`;
- `building`;
- `room_code`;
- `course_code`;
- `start_time_after` and `end_time_before` in `HH:MM`;
- `time_range[start]` and `time_range[end]` in `HH:MM`.

When `time_range` is supplied, both values are required and the end must be
after the start. Date ranges are validated in chronological order.

Response fields are owned by `TimetableController` and `TimetableService`.

The controller contains methods for weekly, session-detail, and filter-option
reads, but those methods are not registered routes. The following old paths
are therefore unsupported:

- `/timetable/weekly`;
- `/timetable/class-session/{classSession}`;
- `/timetable/filter-options`.
