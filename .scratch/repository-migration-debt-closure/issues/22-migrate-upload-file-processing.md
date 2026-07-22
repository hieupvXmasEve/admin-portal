# Migrate shared upload and file-processing flows

Status: completed

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Provide one supported platform boundary for chunked uploads, image uploads, file validation, storage URL generation, and reusable file-processing concerns while leaving domain-specific import/export decisions with their owning contexts.

## Acceptance criteria

- [x] Shared upload initiation, chunk assembly, validation, storage, retrieval URL, and cleanup behavior use one supported platform path.
- [x] Authentication, authorization, file-size/type rules, path isolation, error envelopes, and audit/security behavior remain compatible.
- [x] Domain imports/exports call the shared technical boundary without moving domain validation or business decisions into it.
- [x] Staff, student, and lecturer callers preserve their supported contracts and portal behavior.
- [x] Legacy upload/file utility paths retire only after all HTTP, queue, import/export, and portal callers cut over.

## Blocked by

- [Establish the Migration Debt inventory and regression guards](03-establish-migration-debt-inventory-and-guards.md)

## Comments

- 2026-07-22: Implemented and staged the technical Upload module boundary: HTTP routes retain `/api/uploads/*`, their existing route names, and `/api/v1/student/uploads`; chunk assembly, validation, storage, URL generation, and all cleanup mechanics now flow through `UploadPlatform`. HTTP callers, the cleanup queue job/command, upload resources/model accessors, and existing query/form attachment consumers cut over. The legacy services, controllers, request, and route file are retired. Domain spreadsheet imports retain their own business/template validation and consume upload records only where their domain flows already support them. Portal inspection found no direct student or lecturer upload endpoint consumer, so portal code was not changed; no API field or URL changed. Data impact: none. Rollback: revert the staged migration.
- 2026-07-22: Verification: focused upload tests pass (3 tests, 9 assertions); route inventory preserves all 16 upload routes; `migration-debt:inventory --check` and touched-file Pint pass; TypeScript type-check passes. Repository format check reports pre-existing formatting failures under `resources/`. A repository-wide test run was started and showed passing suites but its terminal result was not returned by the runner; it must be rerun before completion.
- 2026-07-23: Maintainer directed the implementation to continue through completion. This cutover deliberately retains the established JSON payloads and status codes (including legacy validation envelopes) because the issue requires compatible supported contracts; it does not introduce an `ApiResponse` envelope migration. Resource authorization is now an `UploadRecordPolicy`, while the existing `admin` middleware remains the gate for management and avatar routes.
- 2026-07-23: Cutover audit: HTTP uses `app/Modules/Upload/routes/api.php` for all 15 `/api/uploads/*` routes plus `routes/api/v1/student.php` for the unchanged `v1.student.uploads.store` route. Queue/command callers are `app/Modules/Upload/Jobs/CleanupOrphanedFilesJob.php` and `app/Console/Commands/CleanupUploadsCommand.php`. Attachment/query callers are `CreateStudentQueryReplyAction`, `Web/Admin/QueryController`, `QueryTicketService`, `ResponseService`, `AttachmentResource`, `QueryReplyResource`, and `UploadRecord`; all call `UploadPlatform`. No domain import/export caller used the retired upload utilities; imports continue to own their template and business validation. Portal searches across `FE/student-nuxt` and `FE/lecturer-nuxt` found no direct `/api/uploads`, `/api/v1/student/uploads`, or avatar-upload endpoint consumer. Both nested portal repositories were clean; no portal code change was needed because URLs and fields are unchanged.
- 2026-07-23: Characterization verifies route names/prefix, public configuration, legacy multiple/list/avatar validation envelopes, owner policy behavior, and chunk-session ownership (8 tests, 26 assertions). `UploadRecordPolicy` protects upload records, `StudentAvatarTargetPolicy` protects avatar targets, and the upload boundary rejects a mismatched actor for chunk upload/status/cancel. Route listing reports all 16 legacy routes; targeted tests, migration-debt inventory, Pint, and Vue type-check pass. Full suite was run; its runner returned the passing progress stream without a terminal summary. Repository format check remains blocked only by pre-existing `resources/` formatting failures.
