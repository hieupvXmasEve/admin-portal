# Documents (1-n) + mirrored Document type catalog

Status: done

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

Replace the 8 hardcoded `submitted_*` columns with a proper 1-n document list (external link references), typed against a Document type catalog mirrored from the CRM, and surface missing required documents.

End-to-end behavior:

- A new `application_documents` table (1-n): `crm_file_id` (unique, for idempotent ingestion later), `file_type_code`, `file_type_name` (denormalized for display), `page_index`, `original_name`, `link`, `mime_type`, `size`, `status`. Stores **external link references only** — not managed uploads, not `upload_records`. Multiple files per type are allowed (e.g. a multi-page transcript).
- A new `application_document_types` table mirrors the CRM file-type catalog (`code`, `name`, `type`, `required`, `int_required`, `active`, `order`, `step`) with a way to sync it from the CRM.
- Staff UI shows an Application's documents grouped by type and **highlights missing required documents** (respecting `required` / `int_required` for international applicants).

Existing `submitted_*` columns remain for legacy rows until the migration slice (08) moves and drops them.

## Acceptance criteria

- [x] `application_documents` exists with the fields above and supports multiple files per `file_type_code` (no unique on the type; idempotency rides on the unique `crm_file_id`).
- [x] `application_document_types` exists and can be synced from the CRM catalog (idempotent upsert by `code` via `ApplicationDocumentTypeSyncService`).
- [x] The document list and per-type grouping render in the UI from the new table (Documents card on the show screen, fed by `documentChecklist`).
- [x] Missing required documents are surfaced (international applicants honor `int_required` via `ApplicationDocumentType::isRequiredFor()`).
- [x] Feature tests cover document listing, multiple-files-per-type, and the missing-required-documents check.

## Blocked by

- `02-application-lifecycle-core`
