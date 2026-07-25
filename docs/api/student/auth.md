---
title: Student and parent authentication API
description: Login, token rotation, context, and student profile routes.
audience:
    - Student portal developers
    - Parent portal developers
status: current
owner: Identity Team
last_verified: 2026-07-25
scope: student-parent-authentication-api
source_of_truth:
    - app/Modules/Identity/routes/api.php
    - app/Modules/Identity/Http/Api/Student/StudentAuthController.php
    - app/Modules/Identity/Http/Api/Parent/ParentAuthController.php
    - app/Modules/StudentRegistry/Http/Api/Student/ProfileController.php
---

# Student and parent authentication API

## Student endpoints

| Method | Path                                | Authentication                            | Input                                                       |
| ------ | ----------------------------------- | ----------------------------------------- | ----------------------------------------------------------- |
| `POST` | `/api/v1/student/auth/login`        | Guest                                     | `email`, `password`; optional `device_name`, `remember_me`. |
| `POST` | `/api/v1/student/auth/login/google` | Guest                                     | `id_token`; optional `device_name`, `remember_me`.          |
| `POST` | `/api/v1/student/auth/refresh`      | Student or authorized parent bearer token | Optional `device_name`.                                     |
| `POST` | `/api/v1/student/auth/logout`       | Student or authorized parent bearer token | None.                                                       |
| `GET`  | `/api/v1/student/context`           | Student or authorized parent bearer token | None.                                                       |

Successful password and Google login responses place the issued bearer token,
token type, expiry, and student identity in `data`. Student tokens expire eight
hours after issuance. Refresh issues a new token and revokes the current token;
clients must replace the stored token atomically.

## Parent endpoints

| Method | Path                                       | Authentication      | Input                                                       |
| ------ | ------------------------------------------ | ------------------- | ----------------------------------------------------------- |
| `POST` | `/api/v1/student/parent/auth/login`        | Guest               | `email`, `password`; optional `device_name`, `remember_me`. |
| `POST` | `/api/v1/student/parent/auth/login/google` | Guest               | `id_token`; optional `device_name`, `remember_me`.          |
| `POST` | `/api/v1/student/parent/auth/refresh`      | Parent bearer token | Optional `device_name`.                                     |
| `POST` | `/api/v1/student/parent/auth/logout`       | Parent bearer token | None.                                                       |
| `GET`  | `/api/v1/student/parent/context`           | Parent bearer token | None.                                                       |

Parent login succeeds only for an active parent account with at least one
active guardian access grant. The login/context payload exposes the children
authorized by those grants. Parent tokens expire eight hours after issuance
and carry the `parent` ability.

## Student profile endpoints

| Method | Path                                       | Notes                                |
| ------ | ------------------------------------------ | ------------------------------------ |
| `GET`  | `/api/v1/student/profile`                  | Current Student Registry profile.    |
| `PUT`  | `/api/v1/student/profile`                  | Validated by `ProfileUpdateRequest`. |
| `POST` | `/api/v1/student/profile/avatar`           | Validated by `AvatarUploadRequest`.  |
| `GET`  | `/api/v1/student/profile/study-plan`       | Legacy study-plan read model.        |
| `GET`  | `/api/v1/student/profile/academic-history` | Legacy academic-history read model.  |

The profile routes use the student profile rate limiter in addition to the
normal student middleware where registered.

## Errors

- Invalid credentials or an invalid actor token return `401`.
- An actor without access to the selected student returns `403`.
- Invalid request fields return `422` in the standard validation envelope.
- Login rate limiting is enforced by the owning login action.
