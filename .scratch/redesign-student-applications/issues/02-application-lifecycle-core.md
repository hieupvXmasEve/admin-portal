# Application lifecycle core: manual create → approve → reject

Status: ready-for-agent

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

The tracer bullet through the whole stack: a staff member creates a `pending` Application by hand, then Approves it (one atomic action that creates the Student) or Rejects it (with a reason). This establishes the real, accountable lifecycle and removes auto-approve.

End-to-end behavior:

- An Application is created `pending` (auto-approve removed; the status default is no longer `approved`).
- Status is one of `pending`, `enrolled`, `rejected`, validated in the backend (allow-list, not a DB enum).
- **Approve** is a single atomic transaction: create the User + Student + roles from the Application, link it, set `approved_by`/`approved_at`, move the Application to `enrolled`. Any failure rolls everything back — never a half-created Student (ADR-0001).
- **Reject** records `rejected_by`/`rejected_at`/`rejected_reason` and creates no Student.
- `student_code` is taken from the Application as provided (stop generating `SWU…` codes).
- Staff UI: create, list (filter by status/campus/intake), show, Approve, Reject.
- "Who approved/rejected and when" is shown from the new columns; full history comes from the existing activity log via `AuditableModel` (no separate status-events table — ADR-0001).

Schema changes here are additive: add audit columns (`approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejected_reason`) and adjust the status default/validation. Guardians and documents stay on the existing `parent_*` / `submitted_*` columns for now; they move out in slices 05/06.

This slice reworks the conversion path (`StudentApplicationService`) into an Approve action; the old convert/batch-convert flow is replaced.

## Acceptance criteria

- [ ] A new Application is `pending` by default; no path auto-approves.
- [ ] Approve atomically creates User + Student + roles, links `student_id`, sets `approved_by`/`approved_at`, status `enrolled`.
- [ ] A forced failure mid-approve leaves no Student, no User, and the Application still `pending`.
- [ ] Reject sets `rejected_by`/`rejected_at`/`rejected_reason` and creates no Student.
- [ ] New student User accounts are created with a **secure password** (random + forced reset or invite/verification) — the hardcoded `'123456'` is gone.
- [ ] `student_code` comes from the Application; no `SWU…` is generated.
- [ ] Staff can create, list/filter, show, approve, and reject from the UI.
- [ ] Approver/rejecter and timestamp are visible; an activity-log entry records the staff causer.
- [ ] Staff-lifecycle feature tests cover approve (success + atomic rollback) and reject, asserting only external behavior (HTTP + DB + activity log).

## Blocked by

- `01-prefactor-remove-excel-import`
