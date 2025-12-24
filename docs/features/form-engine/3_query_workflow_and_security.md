# Form Engine - Query Workflow & Security

Focuses on the "Query" type forms, where `department` scoping and assignments are critical.

## Security Model

### 1. Visibility (Department Staff)

- A staff member belongs to a `department`.
- When viewing `Query Management`, they should **ONLY** see responses dynamically linked to their department via `form_targets`.

**Logic:**

```sql
SELECT r.*
FROM responses r
JOIN form_targets ft ON r.form_target_id = ft.id
WHERE ft.scope_type = 'department'
  AND ft.scope_id = :current_user_department_id
```

### 2. Assignment Authorization

- Only Staff within the **same department** (as the target) can be assigned.
- Dept Head can assign to anyone in their Dept.

## Workflow APIs

### List Queries (Inbox)

`GET /queries/list`

- Filters: status (`open`, `in_progress`, `resolved`), assignee (`me`, `unassigned`).
- Enforces Department scope automatically.

### View Query Detail

`GET /queries/{ticket}`

- Ticket = Response ID.
- Shows:
    - Form Data (Questions + Answers)
    - Thread History (Messages)
    - Current Status & Assignment

### Update Status

`POST /queries/{ticket}/status`

- Body: `{ status: 'resolved' }`

### Assign Ticket

`POST /queries/{ticket}/assign`

- Body: `{ assigned_to: user_id }`
- Validation: `assigned_to` must be in the same department.

### Reply to Student

`POST /queries/{ticket}/replies`

- Body: `{ message: "..." }`
- Appends to `response_threads`.
- Notifies student.
