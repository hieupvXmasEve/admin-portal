# Form Engine - Student Interaction API

## Fetching Forms

### List Available Forms

`GET /api/v1/student/forms`

- Returns forms available to the student based on their context (e.g., enrolled courses, university-wide surveys).
- Filter by `type` (survey, query).

### Get Form Detail (Render)

`GET /api/v1/student/forms/{form_id}`

- Returns the **Active Published Version**.
- Includes `form_targets` valid for this user (to select context if multiple exist).
    - _Example_: "Feedback for Course A" vs "Feedback for Course B" (Same Form, different Target).

## Submission

### Submit Response

`POST /api/v1/student/forms/{form_id}/submit`

- Body:
    ```json
    {
        "target_id": "uuid-of-target", // Crucial: defines the context (e.g., specific department or course)
        "answers": [{ "question_id": "...", "value": "..." }]
    }
    ```
- Validation:
    - Validate `target_id` is valid for this form and accessible by the student.
    - Validate answers against Question schema.

### My Queries (History)

`GET /api/v1/student/queries`

- List past submitted queries and their status.

### Query Detail & Reply

`GET /api/v1/student/queries/{response_id}`
`POST /api/v1/student/queries/{response_id}/reply`

- Student side of the thread.
