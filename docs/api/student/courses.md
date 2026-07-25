---
title: Student courses API
description: Enrolled courses, curriculum, modules, calendar, and upload entry points.
audience:
    - Student portal developers
status: current
owner: Academic Team
last_verified: 2026-07-25
scope: student-courses-api
source_of_truth:
    - routes/api/v1/student.php
    - app/Http/Controllers/Api/V1/Student/CourseRegistrationController.php
    - app/Http/Controllers/Api/V1/Student/CurriculumController.php
    - app/Http/Controllers/Api/V1/Student/ModuleController.php
    - app/Http/Controllers/Api/V1/Student/CalendarController.php
---

# Student courses API

All endpoints use the `/api/v1/student` base and require the protected student
middleware stack.

## Course offering reads

| Method | Path                                   | Input                                                                                          |
| ------ | -------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `GET`  | `/course/enrolled`                     | Optional `unit_code`, `unit_name`, `lecturer`, `day_of_week`, `time_slot`, `page`, `per_page`. |
| `GET`  | `/course/available`                    | Compatibility alias of `/course/enrolled`; it does not list registerable offerings.            |
| `GET`  | `/course/available/{courseOfferingId}` | Enrolled-course detail compatibility path.                                                     |

The active route file does not expose course registration, course drop, or a
`/courses/{id}/detail` endpoint. Clients must not depend on those former
documentation-only paths.

## Curriculum

| Method | Path                               | Purpose                         |
| ------ | ---------------------------------- | ------------------------------- |
| `GET`  | `/curriculum`                      | Curriculum overview.            |
| `GET`  | `/curriculum/by-semester`          | Curriculum grouped by semester. |
| `GET`  | `/curriculum/program-requirements` | Program requirement summary.    |
| `GET`  | `/curriculum/roadmap`              | Academic roadmap read model.    |

The response structures are owned by `CurriculumController`,
`App\Services\V1\Student\CurriculumService`, and the curriculum resources.

## Modules

| Method | Path                 |
| ------ | -------------------- |
| `GET`  | `/modules`           |
| `GET`  | `/modules/dashboard` |
| `GET`  | `/modules/roadmap`   |
| `GET`  | `/modules/{module}`  |

These routes serve the Finland-campus modular progress model. Module response
fields are owned by `ModuleController` and its resources/services.

## Academic calendar

| Method | Path                                      |
| ------ | ----------------------------------------- |
| `GET`  | `/calendar/semesters`                     |
| `GET`  | `/calendar/semester/{semester}/deadlines` |
| `GET`  | `/calendar/academic-calendar`             |
| `GET`  | `/calendar/current-semester`              |

## Uploads

`POST /uploads` is the student-controlled upload entry point. Its multipart
body requires `file` and `context`; optional metadata is `alt_text`,
`description`, and `expires_at`. Allowed file rules are context-specific and
owned by `App\Modules\Upload\Http\Requests\Upload\UploadRequest`,
`config/uploads.php`, and `UploadController`.
