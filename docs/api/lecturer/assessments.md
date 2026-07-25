---
title: Lecturer assessments and gradebook API
description: Assessment structure, grading, imports, exports, reports, and matrix gradebook saves.
audience:
    - Lecturer portal developers
status: current
owner: Academic Team
last_verified: 2026-07-25
scope: lecturer-assessments-gradebook-api
source_of_truth:
    - routes/api/v1/lecturer.php
    - app/Modules/Academic/Delivery/Http/Api/Lecturer/AssessmentController.php
    - app/Modules/Academic/Delivery/Http/Api/Lecturer/AssessmentReportController.php
    - app/Modules/Academic/Delivery/Http/Api/Lecturer/GradebookController.php
---

# Lecturer assessments and gradebook API

Course assessment base:
`/api/v1/lecturer/courses/{courseOffering}/assessments`

All routes require the protected lecturer middleware stack and controller-level
access to the course offering.

## Assessment structure and direct grading

| Method | Path                                     |
| ------ | ---------------------------------------- |
| `GET`  | `/`                                      |
| `GET`  | `/grade/student/{student}`               |
| `GET`  | `/grade/component/{assessmentComponent}` |
| `PUT`  | `/scores/{score}`                        |
| `POST` | `/scores/bulk-update`                    |
| `GET`  | `/validate-weights`                      |

Single and bulk score bodies are validated by `UpdateGradeRequest` and
`BulkUpdateGradesRequest`. Supported fields and status values are owned by
those Form Requests. A final score must contain points or a percentage, and
points cannot exceed the assessment detail's maximum.

Assessment component/detail create, update, and delete methods exist in the
controller but their routes are commented out. They are not part of the
current API contract.

## Grade table, import, and export

| Method | Path                                                   | Notes                                                                             |
| ------ | ------------------------------------------------------ | --------------------------------------------------------------------------------- |
| `GET`  | `/details/{assessmentComponentDetail}/grades`          | Pagination, sorting, score/status/date filters from `GradeTableRequest`.          |
| `GET`  | `/details/{assessmentComponentDetail}/statistics`      | Grade statistics.                                                                 |
| `POST` | `/details/{assessmentComponentDetail}/bulk-grades`     | Bulk upsert body validated in the controller.                                     |
| `GET`  | `/details/{assessmentComponentDetail}/export-template` | Spreadsheet download.                                                             |
| `POST` | `/details/{assessmentComponentDetail}/import-grades`   | Multipart `file`; optional import controls.                                       |
| `GET`  | `/details/{assessmentComponentDetail}/export`          | `format` defaults to `excel`; supported values are implemented by the controller. |

Imports accept `.xlsx`, `.xls`, or `.csv` files up to 10 MB. Options include
`update_mode`, `overwrite_existing`, `validate_only`, `skip_errors`,
`default_status`, and `default_score_status`; defaults are applied by
`ImportGradesRequest`.

## Reports

| Method | Path                   |
| ------ | ---------------------- |
| `GET`  | `/report/overview`     |
| `GET`  | `/report/grade-matrix` |
| `GET`  | `/report/statistics`   |
| `GET`  | `/report/export/excel` |
| `GET`  | `/report/export/pdf`   |

The export routes return binary downloads. Report filters and options are
validated by `ExportAssessmentRequest`. The JSON report endpoints retain the
response shape implemented directly by `AssessmentReportController`.

## Gradebook

| Method | Path                                                         |
| ------ | ------------------------------------------------------------ |
| `GET`  | `/api/v1/lecturer/courses/{courseOffering}/gradebook`        |
| `POST` | `/api/v1/lecturer/courses/{courseOffering}/gradebook/scores` |

The gradebook response is a matrix containing course context, summary, items,
and student rows. A save request uses:

```json
{
    "scores": [
        {
            "detail_id": 10,
            "student_id": 20,
            "points_earned": 85
        }
    ]
}
```

The save is all-or-nothing. Assessment details must belong to the course
offering syllabus, students must have an eligible registration, duplicate
cells are rejected, and points cannot exceed `max_points`.
