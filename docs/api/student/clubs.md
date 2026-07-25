---
title: Student clubs API
description: Student club discovery, membership, and authorized club management.
audience:
    - Student portal developers
status: current
owner: Engagement Team
last_verified: 2026-07-25
scope: student-clubs-api
source_of_truth:
    - app/Modules/Engagement/routes/api.php
    - app/Modules/Engagement/Http/Api/Student/ClubController.php
    - app/Modules/Engagement/Http/Api/Student/ClubManagementController.php
    - app/Http/Resources/Api/V1/Student/ClubResource.php
    - app/Http/Resources/Api/V1/Student/ClubMemberResource.php
---

# Student clubs API

Base path: `/api/v1/student/clubs`

All routes require an authenticated student or authorized parent proxy.
Discovery is campus-scoped. Management operations additionally require the
club authorization enforced by `ClubManagementController`.

## Discovery and membership

| Method | Path              | Input                                                                            |
| ------ | ----------------- | -------------------------------------------------------------------------------- |
| `GET`  | `/`               | Optional `search`, `status` (default `active`), `page`, `per_page` (maximum 50). |
| `GET`  | `/my-memberships` | Optional `status`, `page`, `per_page` (maximum 50).                              |
| `GET`  | `/{club}`         | None.                                                                            |
| `POST` | `/{club}/apply`   | Optional `application_notes`, maximum 1,000 characters.                          |

List endpoints return club or membership resources with the standard
pagination metadata. A student cannot discover or apply to a club on another
campus.

## Club management

| Method | Path                               | Input                                                 |
| ------ | ---------------------------------- | ----------------------------------------------------- |
| `GET`  | `/{club}/manage`                   | None.                                                 |
| `PUT`  | `/{club}`                          | Club profile fields validated by `UpdateClubRequest`. |
| `GET`  | `/{club}/members`                  | Optional list filters handled by the controller.      |
| `PUT`  | `/{club}/members/{member}/approve` | None.                                                 |
| `PUT`  | `/{club}/members/{member}/reject`  | Rejection input validated by `RejectMemberRequest`.   |
| `PUT`  | `/{club}/members/{member}/role`    | Role input validated by `UpdateRoleRequest`.          |

The update request requires `name` and accepts the current optional profile,
contact, social-link, and achievement fields defined by
`app/Modules/Engagement/Http/Requests/UpdateClubRequest.php`.

## Errors

- A club outside the actor's campus is returned as `404`.
- A non-manager using a management route receives `403`.
- Membership-state conflicts use a `422` business-logic error.
- Invalid request fields return `422`.
