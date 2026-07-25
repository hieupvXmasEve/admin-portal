---
title: Lecturer forms API status
description: Current route status for lecturer-facing forms and surveys.
audience:
    - Lecturer portal developers
    - Engagement maintainers
status: current
owner: Engagement Team
last_verified: 2026-07-25
scope: lecturer-forms-route-status
source_of_truth:
    - routes/api/v1/lecturer.php
    - app/Modules/Engagement/routes/api.php
---

# Lecturer forms API status

There is currently no registered `/api/v1/lecturer/forms` or
`/api/v1/lecturer/surveys` API.

The active portal form routes are student-facing and are documented in
[`docs/api/student/forms.md`](../student/forms.md). Administrative form
management uses authenticated staff routes in
`app/Modules/Engagement/routes/api.php` and web routes in
`app/Modules/Engagement/routes/web.php`; those are not lecturer portal
contracts.

Do not call the old student form examples that were previously stored under
the lecturer documentation directory. Add a lecturer page contract only when
an executable lecturer route, authorization policy, request validation, and
response owner have been implemented.
