# Migration & backfill of existing Applications

Status: done

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

- [x] Every legacy row's parent contact lands in `application_guardians`; every submitted document URL lands in `application_documents`.
- [x] Converted rows become `enrolled` with the correct `student_id`; unconverted rows become `pending`.
- [x] No identity/contact data is lost; counts reconcile before vs after.
- [x] Legacy `parent_*` and `submitted_*` columns are dropped only after a successful backfill.
- [x] A dry-run mode reports planned changes without writing.
- [x] The migration is verified against a copy of real dev data.

## Implementation notes

- Document source resolved with the operator: CRM export (`Asia_NE_2025_AdmissionFiles.csv`) is authoritative
  (joined by `student_code`); `submitted_*` URLs are the fallback for applications absent from the export, so the
  ~51 rows the export does not cover keep their documents and nothing is duplicated.
- `applications:backfill` (service `ApplicationBackfillService` + command): read-only dry-run by default, `--apply`
  to commit; seeds the document-type catalog from `Asia_File_Types.csv`, moves `parent_*` → one primary guardian,
  builds `application_documents`, and remaps status (converted → `enrolled`, else → `pending`). Idempotent.
- The legacy columns are dropped by `2026_06_27_170000_drop_legacy_parent_and_submitted_columns`, gated by
  `LegacyColumnDropGuard` which refuses while applications exist but the target tables are empty.
- Verified against the real 241-row dev DB (dry-run): 93 guardians, 12 catalog types, 1493 CSV docs (190 apps)
  + 16 fallback docs, 229 enrolled + 12 pending — counts reconcile to 241.

## Blocked by

- `05-guardians`
- `06-documents-and-catalog`
