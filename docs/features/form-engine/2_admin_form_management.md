# Form Engine - Admin Management API

Routes handled in `FormController`.

## Form Management

### List Forms

`GET /admin/forms`

- Filter by type, status.
- Pagination.

### Create Form

`POST /admin/forms`

- Body: `{ title, type, slug, description }`

### Get Form Builder Data

`GET /admin/forms/{form}`

- Returns form details + **current draft version** structure (sections, questions).

### Update Form Structure (Builder Save)

`PUT /admin/forms/{form}`

- Defines the entire structure for the **draft version**.
- Body:
    ```json
    {
        "title": "...",
        "sections": [
            {
                "title": "Section 1",
                "questions": [
                    { "type": "text", "label": "Name", ... }
                ]
            }
        ]
    }
    ```

### Publish Version

`POST /admin/forms/{form}/versions/{version}/publish`

- Marks specific version as active.
- Snapshots the schema.

## Target Management (Scoping)

### Add Target

`POST /admin/forms/{form}/targets`

- Body: `{ scope_type: 'department', scope_id: 'IT' }`
- Limits who can see/answer the form and where responses are routed.

### Remove Target

`DELETE /admin/forms/{form}/targets/{target}`

## Query specific settings

If `type === 'query'`, additional settings might be needed in `form_targets` to define default assignees or SLAs.
