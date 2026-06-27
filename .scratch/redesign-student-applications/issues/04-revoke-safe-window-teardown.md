# Revoke a mistaken approval (safe-window teardown)

Status: ready-for-agent

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

Let staff undo a mistaken approval while it is still safe. **Revoke** is only for correcting a recent approval — not for removing a student who has studied (that is Withdraw, out of scope — ADR-0002).

End-to-end behavior:

- Revoke is allowed only when the linked Student has **zero downstream activity** (no charges, scores, enrollments, wallet activity, logins, etc.).
- Within the safe window: a transactional teardown removes the created Student + User + roles, sets `revoked_by`/`revoked_at`, and returns the Application to `pending` for correction and re-approval.
- Outside the window: Revoke is blocked with a clear message directing staff to the (separate) Withdraw path; nothing is deleted.
- Staff UI exposes Revoke on an `enrolled` Application, gated by `revoke_student_application` (from slice 03).

## Acceptance criteria

- [ ] Revoke on an `enrolled` Application with no downstream activity tears down Student + User + roles, sets `revoked_by`/`revoked_at`, and returns the Application to `pending`.
- [ ] Revoke is blocked (clear error, nothing deleted) once any downstream academic/financial record exists for the Student.
- [ ] An activity-log entry records the revoking staff causer.
- [ ] After a revoke, the Application can be edited and approved again.
- [ ] Feature tests cover both the safe-window teardown and the blocked-with-activity case.

## Blocked by

- `02-application-lifecycle-core`
- `03-authorization-permissions-campus-scope`
