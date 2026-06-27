# CRM ingestion: full create/update of Applications, Guardians, Documents

Status: ready-for-agent

## Parent

`.scratch/redesign-student-applications/PRD.md`

## What to build

The full admissions-CRM ingestion API: create and update `pending` Applications (with Guardians and Documents) over the scaffolded, authenticated `/api/v1` path. Ingestion never approves/rejects/revokes — those stay staff-only (ADR-0001/0004).

End-to-end behavior:

- Create an Application from a CRM payload including its Guardians and Document references.
- Idempotent upsert keyed by `crm_admission_id` (Application) and `crm_file_id` (Document) — re-sending the same admission/file updates rather than duplicates.
- Updates are allowed only while the Application is `pending`; an update to an `enrolled` or `rejected` Application returns `409 Conflict` (frozen at approval — ADR-0003).
- The CRM-issued `student_code` is stored as provided.
- Document references resolve against the mirrored Document type catalog.
- All responses use the `ApiResponse` envelope; validation errors are returned in it.

## Acceptance criteria

- [ ] Creating an Application via the API persists the core record plus its Guardians and Document references.
- [ ] Re-sending the same `crm_admission_id` updates the existing Application (no duplicate); re-sending the same `crm_file_id` updates the existing Document (no duplicate).
- [ ] Updating a `pending` Application overwrites fields; updating an `enrolled`/`rejected` Application returns `409 Conflict`.
- [ ] `student_code` from the payload is stored; no code is generated.
- [ ] The API exposes no approve/reject/revoke capability.
- [ ] Ingestion-seam feature tests cover create, idempotent upsert (application + document), update-while-pending, and the 409-after-approve case, using a service token.

## Blocked by

- `07-crm-ingestion-auth-scaffolding`
- `05-guardians`
- `06-documents-and-catalog`
