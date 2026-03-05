# Parent Auth and Data Model (Current State)

Last updated: 2026-03-02  
Owner: Platform Team  
Status: Code-verified snapshot

## API Surface

Defined in `app/Modules/Identity/routes/api.php` under `v1/student/parent`:

- `POST /api/v1/student/parent/auth/login`
- `POST /api/v1/student/parent/auth/login/google`
- `POST /api/v1/student/parent/auth/logout` (protected)
- `POST /api/v1/student/parent/auth/refresh` (protected)
- `GET /api/v1/student/parent/context` (protected)

Protected middleware chain:

- `auth:sanctum`
- `api.logging`
- `api.actor:parent`

## Parent Tables

Created by `database/migrations/2026_01_02_000001_setup_parent_portal.php`:

- `parents`
    - key fields: `user_id` (unique FK), `full_name`, `phone`, `email_snapshot`, `status`
    - includes soft deletes
- `parent_student`
    - key fields: `parent_id`, `student_id`, `relationship`, `is_primary`, `access_level`
    - unique pair on (`parent_id`, `student_id`)

## Model Contracts

- `App\Models\ParentProfile` uses table `parents` and includes `students()` belongsToMany via `parent_student`.
- `App\Models\Student` exposes parent relation via `parent_student` pivot.
- `App\Models\User` exposes `parentProfile()` and `children()` helpers.

## Drift Note

Earlier versions of this document were DDL-first proposals. This version documents what is currently implemented. Keep any future schema proposal in a separate `*-proposal.md` file to avoid mixing design and current-state docs.
