# Guardians (1-n) on Applications

Status: done

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

Replace the thin `parent_phone` / `parent_email` pair with a proper 1-n list of Guardians, and carry the primary Guardian into the Student on approval.

End-to-end behavior:

- A new `application_guardians` table (1-n): `full_name` (required), `relationship` (varchar validated against a backend allow-list — **not** a DB enum), `phone`, `email`, `occupation` (nullable), `address` (nullable), `is_primary` (exactly one primary per Application).
- Staff UI to add/edit/remove Guardians on a `pending` Application.
- On **Approve**, the primary Guardian populates the Student's `emergency_contact_{name,phone,relationship}` and is linked as a Parent account. `handleParentAssignment` is reworked to accept a structured Guardian (real `full_name`, `relationship`, and the possibility of multiple) instead of `email` + `null` name.

Existing `parent_*` columns remain populated for legacy rows until the migration slice (08) moves and drops them.

## Acceptance criteria

- [x] `application_guardians` exists with the fields above; `relationship` is validated in the backend, addable values need no migration.
- [x] Exactly one Guardian can be primary; the constraint is enforced (DB generated-column unique index + service invariant).
- [x] Staff can add/edit/remove Guardians on a `pending` Application via the UI.
- [x] On approve, the primary Guardian maps to the Student emergency contact and creates/links a Parent account with the real name and relationship.
- [x] `handleParentAssignment` handles structured, possibly-multiple Guardians (no `null`-name / hardcoded "Parent" relationship).
- [x] Feature tests cover guardian CRUD and the approve-time mapping/parent linkage.

## Blocked by

- `02-application-lifecycle-core`
