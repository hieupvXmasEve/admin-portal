---
title: Student dashboard API
description: Dashboard aggregates and focused academic summary endpoints.
audience:
    - Student portal developers
status: current
owner: Academic Team
last_verified: 2026-07-25
scope: student-dashboard-api
source_of_truth:
    - routes/api/v1/student.php
    - app/Http/Controllers/Api/V1/Student/DashboardController.php
    - app/Services/V1/Student/DashboardService.php
---

# Student dashboard API

Base path: `/api/v1/student/dashboard`

All endpoints require the protected student middleware stack. The selected
student is resolved from the authenticated student or authorized parent proxy;
clients must not use a student identifier as an authorization mechanism.

## Endpoints

| Method | Path                    | Response data                      |
| ------ | ----------------------- | ---------------------------------- |
| `GET`  | `/`                     | Combined dashboard payload.        |
| `GET`  | `/gpa`                  | GPA summary and trend data.        |
| `GET`  | `/credit-progress`      | Credit and graduation progress.    |
| `GET`  | `/academic-holds`       | Active academic holds and summary. |
| `GET`  | `/upcoming-assessments` | Upcoming assessment summary.       |

The combined payload currently uses these top-level `data` keys:

- `current_semester`;
- `gpa_data`;
- `credit_progress`;
- `academic_holds`;
- `upcoming_assessments`;
- `enrollment_status`;
- `quick_stats`;
- `last_updated`.

The nested contract is owned by
`App\Services\V1\Student\DashboardService`. Consumers should preserve nullable
values: a missing academic calculation is not equivalent to zero.

Failures to build a dashboard section return the standard API error envelope.
