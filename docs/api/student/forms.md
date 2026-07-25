---
title: Student forms and queries API
description: Student surveys, query forms, submissions, and support-ticket threads.
audience:
    - Student portal developers
status: current
owner: Engagement Team
last_verified: 2026-07-25
scope: student-forms-api
source_of_truth:
    - app/Modules/Engagement/routes/api.php
    - app/Modules/Engagement/Http/Api/Student/FormController.php
    - app/Modules/Engagement/Http/Api/Student/QueryTicketController.php
    - app/Modules/Engagement/Http/Requests/Forms/SubmitFormRequest.php
---

# Student forms and queries API

All endpoints use `/api/v1/student` and require the protected student
middleware stack. Form availability is evaluated for the active student and
campus; a client cannot make an unavailable form accessible by changing an
identifier.

## Forms and surveys

| Method | Path                           | Input                                                                    |
| ------ | ------------------------------ | ------------------------------------------------------------------------ |
| `GET`  | `/forms`                       | Required `type`: `feedback`, `survey`, or `query`; optional `campus_id`. |
| `GET`  | `/forms/query/runs`            | Optional `campus_id`.                                                    |
| `GET`  | `/forms/query/{form}`          | Optional `campus_id`.                                                    |
| `POST` | `/forms/query/{form}/submit`   | Form submission payload.                                                 |
| `GET`  | `/forms/surveys/pending`       | None.                                                                    |
| `GET`  | `/forms/surveys/{form}`        | Optional `campus_id`.                                                    |
| `POST` | `/forms/surveys/{form}/submit` | Form submission payload.                                                 |

`GET /forms/query/runs` is the registered query-form collection route. There
is no active `GET /forms/{form}` or `POST /forms/{form}` route.

## Submission payload

`answers` is required. Every answer requires `question_id` and may contain
`answer_text`, `answer_number`, `answer_date`, `comment`, and
`selected_options`. Every selected option requires `option_id` and may include
`free_text`.

Submission context:

- `campus_id` is optional and must be an accessible campus;
- `target_scope_type` defaults to `global` and accepts `global`, `course`,
  `section`, `class_session`, `department`, or `semester`;
- `target_scope_id` is required for a non-global non-query scope;
- `anonymized` defaults to `false`;
- `origin` defaults to `web` and accepts `web`, `mobile`, or `api`.

For a query form, `query` may contain `topic_id`, `custom_topic_text`, and
`priority` (`low`, `normal`, or `high`). If `query` is present, a topic id or
custom topic is required.

## Query tickets

| Method | Path                        | Purpose                                    |
| ------ | --------------------------- | ------------------------------------------ |
| `GET`  | `/queries`                  | List the selected student's query tickets. |
| `GET`  | `/queries/{ticket}`         | Read one authorized ticket and its thread. |
| `POST` | `/queries/{ticket}/replies` | Add a student reply.                       |

The list filters, reply validation, and response fields are owned by
`QueryTicketController`, `ListStudentQueryTicketsRequest`,
`StoreQueryReplyRequest`, and the query-ticket resources.

Inactive students, inaccessible campuses, unavailable forms, and foreign
tickets are rejected by the owning authorization checks.
