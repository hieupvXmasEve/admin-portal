# Error Handling

> **Canonical rules:** `docs/rules/` is the source of truth for coding standards.
> This file supplements the Trellis workflow context only. When in doubt, defer to `docs/rules/`.

> How errors are handled in this project.

---

## Overview

- API requests are routed through a custom JSON exception path in `bootstrap/app.php`.
- Shared API responses use a unified envelope from `app/Http/Responses/ApiResponse.php`.
- Domain and validation failures commonly throw `ValidationException::withMessages(...)`.
- Web and legacy controllers still contain ad hoc `try/catch` blocks, so check nearby code before standardizing a flow.

---

## Error Types

- `ValidationException` for user-correctable input/business validation failures.
- Authentication/authorization exceptions are normalized for JSON APIs.
- `ModelNotFoundException` and `NotFoundHttpException` are mapped to standard API not-found responses.
- `BusinessLogicException` is supported by the API exception handler when a domain flow needs a named business failure.

Examples:

- Central API exception mapping: `app/Exceptions/ApiExceptionHandler.php`
- Shared response envelope: `app/Http/Responses/ApiResponse.php`
- Validation failure from domain action: `app/Modules/Academic/Actions/RecordStudentActionAction.php`

---

## Error Handling Patterns

- Prefer letting framework exceptions bubble into the API handler for JSON endpoints.
- Use `ValidationException::withMessages(...)` when the failure belongs to a field or user action.
- For transactional flows, throw inside `DB::transaction(...)` and let rollback happen automatically.
- Log before swallowing exceptions; many older web paths miss this.

---

## API Error Responses

Standard JSON envelope fields:

- `success`
- `message`
- `errors`
- `timestamp`
- optional `data` / `meta` for success responses

Validation errors are normalized into indexed error items with:

- `code`
- `field`
- `detail`

Source of truth: `app/Http/Responses/ApiResponse.php`

---

## Common Mistakes

- Do not return raw exception messages from production-facing endpoints unless the surrounding code already does and the task is only local cleanup.
- Do not mix envelope styles inside one API surface.
- Do not catch exceptions in web controllers without logging or surfacing enough context.
- Do not assume all JSON endpoints already use the shared handler; verify the route/controller path.
