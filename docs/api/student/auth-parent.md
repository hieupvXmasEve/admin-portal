# Parent Auth and Data Model (Current State)

Last updated: 2026-07-18
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

## Guardian Relationship and Access Tables

Current ownership is split between Student Registry and Identity & Access:

- `student_guardian_relationships` (Student Registry)
    - stores every Guardian relationship, contact facts, relationship type, and primary designation
    - exists independently of email, account, or portal access
- `guardian_access_grants` (Identity & Access)
    - links an account-backed Parent profile to one Registry relationship and Student
    - stores `access_level`, active/revoked status, and grant/revocation timestamps
- `parents` (Identity & Access)
    - key fields: `user_id` (unique FK), `full_name`, `phone`, `email_snapshot`, `status`
    - includes soft deletes

The legacy `parent_student` table remains as a transitional compatibility projection for unmigrated consumers. Authentication, token refresh, parent context, and parent-proxy Student authorization do not evaluate it.

The 2026-07-18 expand/backfill migrations restore every Guardian from approved Applications, reconcile matching account-backed `parent_student` rows by Student and email without duplicates, and retain relationship type, primary designation, and access level.

## Model Contracts

- Registry readers/writers are exposed through `App\Shared\Contracts\StudentRegistry`.
- Identity grant readers/writers are exposed through `App\Shared\Contracts\Identity`.
- Parent API responses retain the existing `children` shape, but the allowed Student ids come from active Identity grants.

## Drift Note

Earlier versions of this document were DDL-first proposals. This version documents what is currently implemented. Keep any future schema proposal in a separate `*-proposal.md` file to avoid mixing design and current-state docs.
