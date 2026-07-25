---
title: Student grades and attendance API
description: Grades, assessment details, GPA trend, academic records, and attendance reads.
audience:
    - Student portal developers
status: current
owner: Academic Team
last_verified: 2026-07-25
scope: student-grades-attendance-api
source_of_truth:
    - routes/api/v1/student.php
    - app/Http/Controllers/Api/V1/Student/GradeController.php
    - app/Modules/Academic/Progression/Http/Api/Student/GpaTrendController.php
    - app/Modules/Academic/Progression/Http/Api/Student/AcademicRecordController.php
    - app/Modules/Academic/Delivery/Http/Api/Student/AttendanceController.php
---

# Student grades and attendance API

All endpoints use `/api/v1/student` and require the protected student
middleware stack.

## Grades and academic records

| Method | Path                                | Input                                                                                                                 |
| ------ | ----------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `GET`  | `/grades`                           | Optional `semester_id`, `completion_status`, `grade`, `unit_code`, `min_gpa`, `max_gpa`, `sort_by`, `sort_direction`. |
| `GET`  | `/grades/gpa-trend`                 | Optional `semester_count`, from 1 through 20; default 8.                                                              |
| `GET`  | `/grades/assessments`               | Controller-owned semester context.                                                                                    |
| `GET`  | `/grades/assessment/{assessmentId}` | None.                                                                                                                 |
| `GET`  | `/academic-records`                 | None.                                                                                                                 |

The main grade response is built by `GradeController`,
`App\Services\V1\Student\GradeService`, and `GradeResource`. GPA trend and
academic-record payloads come from the shared Academic reader contracts used
by their module controllers.

Nullable scores, grade labels, and progress values must remain nullable in the
client. A missing academic result is not zero and is not a failed result.

## Attendance

| Method | Path                                    | Input                   |
| ------ | --------------------------------------- | ----------------------- |
| `GET`  | `/attendance/report`                    | Optional `semester_id`. |
| `GET`  | `/attendance/course/{courseOfferingId}` | None.                   |

The active student route file does not register `/attendance`,
`/attendance/summary`, or `/attendance/alerts`. Attendance report response
fields are owned by `AttendanceReportResource`; course attendance fields are
owned by `GetStudentAttendanceQuery`.

## Errors

Invalid filters return `422`. A missing or inaccessible assessment/course
returns the controller's business-logic or not-found response. Unexpected read
failures use the standard `500` API envelope.
