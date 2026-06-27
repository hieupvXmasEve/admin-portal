# Approve is a single atomic step that creates the Student

In the redesigned admissions flow, **Approve** is one transactional action that turns a `pending` Application into a Student (creates the User + Student + roles, links Guardians). There is no separate "review" step, because the admissions CRM already does the screening upstream — Swinx's job is to confirm enrollment, not re-review.

Approve/Reject/Revoke are **staff-only** actions (web UI), guarded by campus-scoped permissions (`approve_student_application`, etc.); the CRM API never approves.

"Who approved" is recorded as explicit business-fact columns on the Application (`approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejected_reason`, `revoked_by`, `revoked_at`). Full transition history relies on the existing `activity_log` (causer + diffs) — we deliberately do **not** add a separate `application_status_events` table, to avoid duplicating `AuditableModel`.
