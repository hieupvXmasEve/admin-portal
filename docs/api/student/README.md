---
title: Student API
description: Canonical navigation for the student and parent-proxy API.
audience:
    - Student portal developers
    - Backend maintainers
status: current
owner: Platform Team
last_verified: 2026-07-25
scope: student-api-navigation
source_of_truth:
    - routes/api.php
    - routes/api/v1/student.php
    - app/Modules/Identity/routes/api.php
    - app/Modules/Engagement/routes/api.php
---

# Student API

Base path: `/api/v1/student`

Protected routes require `auth:sanctum`, API logging, an allowed student or
parent actor, and the student-access middleware selected by the token actor.
Parent tokens can access student data only through active guardian access
grants.

## Documentation map

| Area                                                | Page                                 |
| --------------------------------------------------- | ------------------------------------ |
| Student and parent authentication, context, profile | [Authentication](auth.md)            |
| Clubs                                               | [Clubs](clubs.md)                    |
| Enrolment, curriculum, modules, calendar            | [Courses](courses.md)                |
| Dashboard aggregates                                | [Dashboard](dashboard.md)            |
| Events                                              | [Events](events.md)                  |
| Settlement, invoices, DNG requests, Gold wallet     | [Finance](finance.md)                |
| Surveys, query forms, query tickets                 | [Forms](forms.md)                    |
| Grades, academic records, attendance                | [Grades](grades.md)                  |
| Timetable                                           | [Timetable](timetable.md)            |
| Notifications and preferences                       | [Notifications](../notifications.md) |

## Contract owners

The complete route inventory is distributed across:

- `routes/api/v1/student.php`;
- `app/Modules/Identity/routes/api.php`;
- `app/Modules/Engagement/routes/api.php`.

Response fields are owned by the controller, resource, action, or query named
by the registered route. The standard envelope and validation error format are
documented in [`docs/api/README.md`](../README.md).

## Parent proxy requests

For protected student endpoints, parent selection is enforced by
`parent.student.access`. Clients must use the selection mechanism implemented
by that middleware and must not treat an arbitrary student identifier as
authorization. The parent context endpoint is the source for currently
accessible children.
