---
title: Lecturer courses and students API
description: Lecturer course offerings, rosters, course summaries, and student management.
audience:
    - Lecturer portal developers
status: current
owner: Academic Team
last_verified: 2026-07-25
scope: lecturer-courses-students-api
source_of_truth:
    - routes/api/v1/lecturer.php
    - app/Http/Controllers/Api/V1/Lecturer/CourseController.php
    - app/Http/Controllers/Api/V1/Lecturer/StudentController.php
    - app/Http/Requests/Api/V1/Lecturer/CourseFilterRequest.php
---

# Lecturer courses and students API

All endpoints use `/api/v1/lecturer` and require the protected lecturer
middleware stack.

## Course offerings

| Method | Path                                   | Input                                                                                         |
| ------ | -------------------------------------- | --------------------------------------------------------------------------------------------- |
| `GET`  | `/courses`                             | Optional `semester_id`, `delivery_mode`, `enrollment_status`, `search`, `per_page` (5 to 50). |
| `GET`  | `/courses/summary`                     | Optional `semester_id`.                                                                       |
| `GET`  | `/courses/filter-options`              | None.                                                                                         |
| `GET`  | `/courses/{courseOffering}`            | None.                                                                                         |
| `GET`  | `/courses/{courseOffering}/unit`       | None.                                                                                         |
| `GET`  | `/courses/{courseOffering}/statistics` | None.                                                                                         |
| `GET`  | `/courses/{courseOffering}/students`   | Optional `search`, `attendance_status`, `sort_by`, `sort_direction`.                          |
| `GET`  | `/courses/{courseOffering}/sessions`   | None.                                                                                         |

`delivery_mode` accepts `in_person`, `online`, `hybrid`, or `blended`.
`enrollment_status` accepts `open`, `closed`, or `full`.

The course list uses the standard pagination metadata. Detail and roster
responses are owned by `CourseController`,
`App\Services\V1\Lecturer\LecturerCourseService`, and the lecturer course
resources. The controller verifies that the authenticated lecturer can access
the course offering.

## Cross-course student management

| Method   | Path                               |
| -------- | ---------------------------------- |
| `GET`    | `/students`                        |
| `GET`    | `/students/summary`                |
| `GET`    | `/students/alerts`                 |
| `GET`    | `/students/requires-attention`     |
| `GET`    | `/students/analytics`              |
| `POST`   | `/students/bulk-actions`           |
| `GET`    | `/students/{student}`              |
| `POST`   | `/students/{student}/notes`        |
| `PUT`    | `/students/{student}/notes/{note}` |
| `DELETE` | `/students/{student}/notes/{note}` |

The list filters, bulk-action body, note body, and response fields are owned by
`StudentController`, `StudentFilterRequest`, and `StudentNoteRequest`.
Lecturers can access only students and notes within the controller's teaching
scope.
