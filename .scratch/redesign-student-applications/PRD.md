# PRD: Redesign the student-applications feature

Status: ready-for-agent

> Source: grilling session (grill-with-docs). Glossary terms in `CONTEXT.md` (Application, Applicant, Approve, Reject, Revoke, Guardian, Document type). Decisions recorded in `docs/adr/0001`–`0004`. Use the glossary vocabulary throughout; respect those ADRs.

## Problem Statement

Staff need to bring prospective students into Swinx from the admissions CRM and admit them. Today's `student_applications` is one fat ~40-column table that:

- mixes applicant identity, contact, a thin pair of parent fields, admission intent, English-test scores, 8 hard-coded `submitted_*` document columns, and conversion fields all in one row;
- is **born `approved`** (status defaults to `approved`, conversion = approval), so there is no real, accountable admit step;
- **records no actor** — there is no first-class "who admitted this student, and when";
- **cannot be rolled back** — approval creates a `User` + `Student` + roles with no safe undo;
- **lacks guardian data** — only `parent_phone` / `parent_email`, no name, relationship, or multiple guardians;
- carries an Excel **import** path and auto-generates `SWU…` student codes that no longer fit the CRM-fed reality.

## Solution

Replace the fat table with a slim Application core plus focused related tables, and give the Application a real, accountable lifecycle.

- Two ingestion channels feed Applications: the **admissions CRM** (server-to-server API) and **manual staff entry** (web UI). The Excel import path is removed.
- An Application is **`pending`** until a staff member explicitly **Approves** it (one atomic step that creates the Student) or **Rejects** it (with a reason). A mistaken approval can be **Revoked** within a safe window. Auto-approve is removed.
- Every lifecycle transition records the acting staff member; "who approved" is a first-class fact.
- Guardian data becomes a proper 1-n list. Documents become a proper 1-n list of external link references, typed against a catalog mirrored from the CRM, so missing required documents can be surfaced.

See ADRs: `0001` (approve = single atomic step; staff-only; audit shape), `0002` (Revoke vs Withdraw; no hard-delete after activity), `0003` (frozen at approval), `0004` (CRM ingestion via Sanctum service token; document catalog).

## User Stories

**CRM ingestion (admissions CRM, server-to-server)**

1. As the admissions CRM, I want to create an Application via API, so that a prospective student appears in Swinx without manual re-entry.
2. As the admissions CRM, I want creates/updates to be idempotent by `crm_admission_id`, so that re-sending the same admission does not create duplicates.
3. As the admissions CRM, I want to update an Application while it is `pending`, so that corrected applicant data overwrites the previous values.
4. As the admissions CRM, I want my update to an already-approved or rejected Application to be refused with a clear `409 Conflict`, so that I know the record is frozen and must not be changed.
5. As the admissions CRM, I want to send one or more Guardians with an Application, so that parent/guardian contact data is captured.
6. As the admissions CRM, I want to send document references (external links) with type, page index, and metadata, so that multiple files per document type are recorded.
7. As the admissions CRM, I want document references to be idempotent by `crm_file_id`, so that re-sends do not duplicate files.
8. As the admissions CRM, I want to authenticate with a scoped service token, so that only the admissions-ingestion capability is exposed to me and nothing else.
9. As the admissions CRM, I want validation errors returned in the standard response envelope, so that I can correct and retry.
10. As the admissions CRM, I want to send the CRM-issued `student_code`, so that Swinx uses it rather than generating its own code.
11. As a platform operator, I want every ingestion call audit-logged (who/when/which admission), so that machine-originated changes are traceable.
12. As a platform operator, I want the ingestion endpoints rate-limited and IP-allowlisted to the CRM, so that the surface is hardened.

**Manual staff entry (web UI)**

13. As an Academic staff member, I want to create an Application manually, so that I can admit a student whose data did not come through the CRM.
14. As an Academic staff member, I want to add/edit Guardians and document references on a manual Application, so that the record is complete.
15. As an Academic staff member, I want to edit a `pending` Application, so that I can fix data before approving.
16. As an Academic staff member, I want manual entry validated the same way as ingestion, so that both channels produce consistent records.

**Review & lifecycle (staff)**

17. As an Academic staff member, I want to see an Application's full data (identity, contact, intent, English test, Guardians, documents) on one screen, so that I can decide.
18. As an Academic staff member, I want to see which required documents are missing (per the document-type catalog), so that I do not approve an incomplete dossier.
19. As an Academic staff member, I want to Approve a `pending` Application in one action, so that it becomes an enrolled Student (User + Student + roles + linked Guardians) atomically.
20. As an Academic staff member, I want Approve to fail cleanly and change nothing if any step fails, so that I never get a half-created Student.
21. As an Academic staff member, I want to Reject a `pending` Application with a reason, so that the decision and its rationale are recorded.
22. As an Academic staff member, I want to Revoke a mistaken approval while the new Student has no downstream activity, so that the Student/User/roles are torn down and the Application returns to `pending` for correction.
23. As an Academic staff member, I want Revoke to be blocked once the Student has any downstream academic/financial activity, with a clear message, so that I never destroy academic or financial records.
24. As an Academic staff member, I want the primary Guardian pushed to the Student's emergency contact and linked as a Parent account on approval, so that parent access works without re-entry.

**Authorization & scoping**

25. As an administrator, I want Approve/Reject/Revoke gated by dedicated permissions, so that only authorized roles can perform them.
26. As an administrator, I want those permissions granted to the Academic department's role(s), so that only Academic staff can admit.
27. As an Academic staff member, I want to act only on Applications for campuses I am permitted at, so that campus boundaries are respected.
28. As a non-authorized user, I want lifecycle actions to be forbidden, so that the workflow cannot be bypassed.

**Audit & visibility**

29. As an Academic staff member, I want to see who approved/rejected/revoked an Application and when, so that decisions are accountable.
30. As an Academic staff member, I want to see the rejection reason on a rejected Application, so that I understand why.
31. As an Academic staff member, I want to list and filter Applications by status, campus, and intake, so that I can work a queue.
32. As an auditor, I want the full transition history available via the existing activity log, so that repeated revoke/approve cycles are traceable.

**Migration**

33. As a platform operator, I want existing `student_applications` rows migrated into the new structure without data loss, so that history is preserved.
34. As a platform operator, I want already-converted applications mapped to `enrolled` and linked to their Student, so that the new lifecycle reflects reality.

## Implementation Decisions

**Schema (decompose the fat table)**

- `student_applications` (slim core): applicant identity (full_name, gender, ethnicity, birth_*, national_id), contact (phone, email, address, health_information), admission intent (campus, intended_program, intended_specialization, intake, is_international_applicant, exception_units, sut_id, english_qualifications, study_link_status), single English-test result inline (test_type, exam_date, listening, reading, writing, speaking, overall), external key `crm_admission_id` (unique), CRM-issued `student_code`, `status`, link `student_id`, and audit columns `approved_by`/`approved_at`/`rejected_by`/`rejected_at`/`rejected_reason`/`revoked_by`/`revoked_at`.
- `application_guardians` (1-n): full_name (required), relationship (varchar, BE-validated allow-list — NOT a DB enum), phone, email, occupation (nullable), address (nullable), is_primary (exactly one primary).
- `application_documents` (1-n): `crm_file_id` (unique), file_type_code, file_type_name (denormalized), page_index, original_name, link, mime_type, size, status. External link references only — **not** `upload_records`. Multiple files per type allowed.
- `application_document_types`: a mirror of the CRM file-type catalog (code, name, type, required, int_required, active, order, step), synced from the CRM so required/optional documents can be validated and "missing document" gaps surfaced.
- `status` values validated in BE (allow-list), not a DB enum. Application statuses: `pending`, `enrolled`, `rejected`. (`withdrawn` is **not** an Application status — see Out of Scope.)

**Lifecycle (state machine)**

```
pending  ──approve──▶ enrolled    (atomic: create User+Student+roles, link Guardians; set approved_by/at)
pending  ──reject───▶ rejected    (set rejected_by/at/reason; no Student created)
enrolled ──revoke───▶ pending     (safe window only: teardown Student+User+roles; set revoked_by/at)
```

- Approve is a single atomic transaction; partial failure rolls back entirely (no half-created Student) — ADR-0001.
- Revoke is permitted only when the linked Student has **zero** downstream activity; otherwise blocked — ADR-0002.

**Ingestion API contract** (ADR-0004)

- `/api/v1` admissions ingestion endpoints, `ApiResponse` envelope. Create + update-while-pending only; **no** approve/reject/revoke over the API.
- Idempotent upsert by `crm_admission_id` (Application) and `crm_file_id` (document).
- Updates to `enrolled`/`rejected` Applications return `409 Conflict` (frozen — ADR-0003).
- Auth: Sanctum personal access token on a dedicated service-account `User` (`UserType::SERVICE`, new enum case) with ability `admissions:ingest`; dedicated rate-limit, IP allowlist, ingestion audit log.
- `student_code` is taken from the CRM; stop generating `SWU…` codes.

**Authorization** 

- New campus-scoped permissions `approve_student_application`, `reject_student_application`, `revoke_student_application`, enforced via a `StudentApplication` policy on the staff web routes. Granted to the Academic department's role(s). Manual create/edit continue to use existing `*_student_application` permissions; the `import_*` permission and routes are removed.

**Audit** (ADR-0001)

- Explicit business-fact columns on the core (above) for quick query/display; full transition history via the existing `activity_log` (causer + diffs through `AuditableModel`). No separate `application_status_events` table.

**Code to rework**

- `StudentApplicationService` (conversion path) and `handleParentAssignment` must be reworked: approve consumes structured Guardians (real `full_name`, `relationship`, possibly several) instead of `email` + `null` name; the convert/approve action replaces the current convert/batch-convert flow.
- Remove the Excel import service, controller actions, routes, and template export.

## Testing Decisions

A good test here exercises **external behavior through the highest seam** — a real HTTP request in, observable outcomes asserted (HTTP status, DB rows, linked records, activity-log entries). Tests must not call services directly or assert that an internal method ran, so the planned rework of `StudentApplicationService` / `handleParentAssignment` does not break them. Pest feature tests, per repo convention (most tests are feature tests).

Two seams (close to ideal; two genuine channels):

1. **Ingestion API seam** — HTTP tests against the `/api/v1` admissions endpoints with a Sanctum service token: create; idempotent upsert by `crm_admission_id`/`crm_file_id`; update-while-pending overwrites; `409` after approve; Guardians and documents persisted; document-type catalog mirrored; validation errors in the envelope; unauthorized/insufficient-ability rejected.
2. **Staff lifecycle seam** — HTTP tests against the staff web routes: approve creates Student+User+roles+linked Guardians and sets `approved_by`/`status=enrolled`; atomic rollback on failure; reject sets reason and creates no Student; revoke within the safe window tears down and returns to `pending`; revoke blocked once downstream activity exists; permission enforcement and campus scoping (403s); activity-log entry with the staff causer.

Prior art: there is essentially no existing test for student-applications (only `tests/Feature/Finance/.../VoucherApplicationForeignKeyTest.php`), so follow general Feature-test conventions in `tests/Feature/**` and use model factories (extend `StudentApplicationFactory`; add factories/states for guardians, documents, and a `pending` state).

## Out of Scope

- **Withdraw** (removing a Student who has already studied). It is a Student-lifecycle capability — logic already exists in `StudentStatusService::updateStatus` and only lacks an entry-point — built separately when needed. It is not triggered from the admissions flow, so `withdrawn` is not an Application status here. See ADR-0002.
- A full applicant-facing self-submission portal (applicants do not submit directly; the CRM and staff are the only channels).
- Any redesign of the downstream `Student`, finance, or academic modules beyond the rework needed to approve/revoke.
- Excel import/template (being removed, not redesigned).

## Further Notes

- **Security to address during build:** the current `User` creation on conversion hardcodes the password `'123456'`. The redesigned approve flow must not ship a hardcoded/default password; use a secure mechanism (random + forced reset, or invite/verification flow).
- **Migration/backfill** of existing `student_applications` rows is required and non-trivial (split into core + guardians + documents; map converted rows to `enrolled` + `student_id`; preserve `national_id`/contact). Plan it as its own issue with a dry-run.
- The single English-test result is inline by decision; if the CRM ever needs to send multiple results, revisit (would become a 1-n table).
