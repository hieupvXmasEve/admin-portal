# Migration & backfill of existing Applications

Status: ready-for-agent

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

Migrate existing `student_applications` rows into the decomposed structure without data loss, then drop the now-moved legacy columns. Done last, once all target tables and write-paths are proven.

End-to-end behavior:

- Move `parent_phone` / `parent_email` into `application_guardians` (a single primary Guardian per legacy row where data exists).
- Move the 8 `submitted_*` document URLs into `application_documents` with their corresponding `file_type_code`.
- Map already-converted applications to `enrolled` linked to their existing `student_id`; map the rest to `pending` (auto-approve removed). Preserve `national_id`, contact, and identity data.
- After backfill, drop the legacy `parent_*` and `submitted_*` columns from the core table.
- Provide a dry-run that reports what would change before committing.

## Acceptance criteria

- [ ] Every legacy row's parent contact lands in `application_guardians`; every submitted document URL lands in `application_documents`.
- [ ] Converted rows become `enrolled` with the correct `student_id`; unconverted rows become `pending`.
- [ ] No identity/contact data is lost; counts reconcile before vs after.
- [ ] Legacy `parent_*` and `submitted_*` columns are dropped only after a successful backfill.
- [ ] A dry-run mode reports planned changes without writing.
- [ ] The migration is verified against a copy of real dev data.

## Blocked by

- `05-guardians`
- `06-documents-and-catalog`
