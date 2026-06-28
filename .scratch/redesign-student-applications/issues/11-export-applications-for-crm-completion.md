# Export Applications for CRM completion

Status: done

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

Legacy migrated rows are missing fields/documents. Rather than an in-app update
path (which would collide with the freeze-at-approval contract — ADR-0003 — and
the revoke-after-activity block — ADR-0002), staff **export** the Applications to
Excel and hand the file to the admissions CRM, which supplies the missing data.
The export is **read-only**; no Application is mutated.

End-to-end behavior:

- One row per Application: CRM matching keys first (`student_code`,
  `crm_admission_id`), then identity / contact / admission intent / English-test,
  lifecycle status, the linked Student, the **primary Guardian**, then **one
  column per active document type** (catalog order), then timestamps.
- **Missing values are left blank, not `N/A`**, so the CRM sees exactly which
  fields and document types are absent.
- A document-type column shows the file link(s) when present, blank when missing.
- Triggered from a campus-scoped **Export Excel** button on the Applications list;
  `scope=filtered` honours the active filters (no filters → the whole campus).
  Export never crosses the current campus boundary.

This reuses the pre-existing `student-applications.export` route + controller
action; the work was on `StudentApplicationExport` (blanks, CRM keys, primary
guardian, per-document-type columns) plus the list-page button.

## Acceptance criteria

- [x] Export has one row per Application with CRM keys (`student_code`,
  `crm_admission_id`) as the leading columns.
- [x] Missing optional fields export as blank cells (no `N/A` placeholder).
- [x] Primary Guardian columns (name, relationship, phone, email) are included.
- [x] One column per active document type; blank means that document is missing.
- [x] Export is campus-scoped and honours the list filters via `scope=filtered`.
- [x] Campus-scoped **Export Excel** button on the Applications list page.
- [x] Feature tests cover blanks-not-N/A, CRM keys, primary guardian, per-type
  document columns, and the campus-scoped xlsx download
  (`tests/Feature/StudentApplication/ExportTest.php`).

## Out of scope / follow-up

- No in-app **update** path for frozen (`enrolled`) Applications — by decision.
- **Open question (deferred):** once the CRM completes the data, re-ingesting it
  for the ~229 legacy rows already `enrolled` is blocked by the freeze (409,
  ADR-0003) and revoke is blocked after activity (ADR-0002). Decide later
  whether completed legacy data stays CRM-side only, applies to `pending` rows
  only, or warrants a dedicated "legacy dossier completion" path.

## Blocked by

- `02-application-lifecycle-core`
- `05-guardians`
- `06-documents-and-catalog`
