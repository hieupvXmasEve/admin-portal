---
title: Lecturer authentication API
description: Lecturer login, Google login, token rotation, profile, and Science-status check.
audience:
    - Lecturer portal developers
status: current
owner: Identity Team
last_verified: 2026-07-25
scope: lecturer-auth-api
source_of_truth:
    - app/Modules/Identity/routes/api.php
    - app/Modules/Identity/Http/Api/Lecturer/LecturerAuthController.php
    - app/Modules/Identity/Actions/LecturerLoginAction.php
---

# Lecturer authentication API

## Guest endpoints

| Method | Path                                  | Input                                                       |
| ------ | ------------------------------------- | ----------------------------------------------------------- |
| `POST` | `/api/v1/lecturer/auth/login`         | `email`, `password`; optional `device_name`, `remember_me`. |
| `POST` | `/api/v1/lecturer/auth/login/google`  | `id_token`; optional `device_name`, `remember_me`.          |
| `GET`  | `/api/v1/lecturer/auth/check-science` | Required query fields `email` and `employee_id`.            |

Password login requires an active user and an Identity-owned lecturer access
grant. A successful login places `token` and `lecturer` in the standard
response `data`.

## Protected endpoints

| Method | Path                            | Input                   |
| ------ | ------------------------------- | ----------------------- |
| `POST` | `/api/v1/lecturer/auth/refresh` | Optional `device_name`. |
| `POST` | `/api/v1/lecturer/auth/logout`  | None.                   |
| `GET`  | `/api/v1/lecturer/auth/me`      | None.                   |

These routes require the lecturer middleware stack. Refresh issues a new token
and revokes the current token; clients must replace the stored bearer token
atomically. The token expiry is supplied by the issuing action/controller and
must be honored by the client.

`GET /auth/me` is the canonical current-lecturer profile endpoint. The exact
profile fields are owned by `LecturerAuthController` and the Identity lecturer
reader/issuer contracts.

Invalid credentials or actor tokens return `401`; inactive or unauthorized
accounts are rejected by the owning action; invalid request fields return
`422`.
