---
title: Swinx API documentation
description: Canonical navigation for the maintained Swinx HTTP API contracts.
audience:
    - API consumers
    - Portal developers
    - Backend maintainers
status: current
owner: Platform Team
last_verified: 2026-07-25
scope: api-navigation
source_of_truth:
    - routes/api.php
    - app/Modules/*/routes/api.php
    - app/Http/Responses/ApiResponse.php
---

# Swinx API documentation

This directory documents the supported integration surfaces. Route files,
controllers, Form Requests, resources, and tests remain the executable source
of truth. Verify the live route inventory with:

```bash
./scripts/dev.sh artisan route:list --path=api --except-vendor
```

## API areas

- [Admissions CRM ingestion](admissions/ingestion.md)
- [Student API](student/README.md)
- [Lecturer API](lecturer/README.md)
- [Student notifications](notifications.md)

## Authentication

Student and lecturer portal APIs use Sanctum bearer tokens. Except for login
and explicitly public routes, send:

```http
Authorization: Bearer <token>
Accept: application/json
```

Actor middleware further limits tokens to the relevant student, parent, or
lecturer surface. The authentication pages document guest and protected
endpoints for each portal.

## Standard JSON envelope

Most JSON endpoints use `App\Http\Responses\ApiResponse`.

```json
{
    "success": true,
    "data": {},
    "meta": {},
    "message": "Optional message",
    "timestamp": "2026-07-25T00:00:00.000000Z"
}
```

`data`, `meta`, and `message` are omitted when they do not apply. Paginated
responses put `page`, `per_page`, `total`, and `total_pages` in `meta`.

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": [
        {
            "code": "VALIDATION_ERROR",
            "field": "email",
            "detail": "The email field is required."
        }
    ],
    "timestamp": "2026-07-25T00:00:00.000000Z"
}
```

Some established endpoints intentionally use compatibility envelopes or file
downloads. Those exceptions are identified on the owning page.

## Contract maintenance

- Keep paths and middleware aligned with the route inventory.
- Keep request fields aligned with Form Requests or controller validation.
- Keep response fields aligned with resources, presenters, or query return
  values.
- Do not document commented-out routes as available.
- Update the matching portal types and consumers in the same work window when
  an API contract changes; see `docs/portal-repos.md`.
