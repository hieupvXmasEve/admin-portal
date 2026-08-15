---
phase: 4
title: "UploadRecord shim sweep"
status: pending
priority: P1
effort: "2d"
dependencies: [2]
---

# Phase 4: UploadRecord shim sweep

## Overview

Delete the UploadRecord shim (owner: Upload). Heaviest phase — red-team corrected two plan errors: (1) the FormResponse relation is **`attachments()`** (`FormResponse.php:113-116`, hasMany FK `response_id`) — there is no `uploads()`; (2) both Engagement relations have LIVE consumers across admin query inbox, student API, and workflow eager-load chains. Also 6 files in `app/Models` bind `UploadRecord::class` unqualified.

## Requirements

- Functional: query-reply attachments + form-response attachments render identically (admin Inertia pages, student API payloads).
- Non-functional: no Upload model import inside Engagement; **contract must NOT be id-addressable** — `UploadRecordPolicy::view` (owner/admin/public-context, branding denied) is currently enforced by FK navigation, and a bare `summariesByIds(array $uploadIds)` would bypass it (enumeration oracle over other students' medical/defer attachments).

## Architecture

- **Live consumers to reroute** (enumerated, not "inventory later"):
  - `app/Modules/Engagement/Http/Web/Admin/QueryController.php:83-88` — `load([... 'attachments'])` + `'queryTicket.replies.uploadRecord'`
  - `app/Modules/Engagement/Http/Web/Admin/QueryTicketController.php:94-104` — nested `'answers.attachments'`, `'attachments'`, `'replies.uploadRecord'`
  - `app/Modules/Engagement/Http/Api/Student/QueryTicketController.php:76-82`
  - `app/Modules/Engagement/Support/QueryTicketWorkflow.php:100,144,175` — `$reply->load(['uploadRecord', ...])`
  - `app/Modules/Engagement/Http/Resources/QueryReplyResource.php:25-28,51-66` — guards on `relationLoaded('uploadRecord')`; **fails SILENT** if relation vanishes (attachment key omitted, HTTP 200)
  - `app/Modules/Engagement/Http/Resources/QueryTicketResource.php:104-107` — `whenLoadedResponseAttachments` returns `[]` on missing relation (silent)
- **Contract shape**: keyed on OWNING entity so FK stays the authz boundary — `app/Shared/Contracts/Upload/UploadRecordReader` with `byReplyIds(array $replyIds): array<int, Summary>` / `byResponseIds(array $responseIds): array<int, list<Summary>>` (batch, keyed maps — no N+1, no id-list signature). Eloquent impl in Upload + ServiceProvider bind, per `ApplicationDocumentCatalogReader` precedent. Where a consumer only needs URL/name of an ALREADY-authorized record, the existing `FileUploadGateway` surface is acceptable.
- **Resources**: rewrite `QueryReplyResource`/`QueryTicketResource` to consume pre-fetched summary maps instead of `relationLoaded` checks.
- **Unqualified bindings** in `app/Models` (bare `UploadRecord::class`): add explicit module `use` — relations stay (global legacy models may reference module namespaces).
- `app/Models/Answer.php` `attachments()` (hasMany, `answer_id`): global-namespace model → namespace swap only, NOT a boundary violation, no contract.
- **Morph gate**: proceed only if Phase 1 pre-flight cleared `App\Models\UploadRecord` in `*_type` columns.

## Related Code Files

- Modify: `app/Modules/Engagement/Models/QueryReply.php` (~58-61), `app/Modules/Engagement/Models/FormResponse.php` (~113-116) — drop `use App\Models\UploadRecord` + relations `uploadRecord()` / `attachments()`
- Modify (reroute, listed above): `QueryController.php`, `QueryTicketController.php` (Web Admin), `QueryTicketController.php` (Api/Student), `QueryTicketWorkflow.php`, `QueryReplyResource.php`, `QueryTicketResource.php`
- Create: `app/Shared/Contracts/Upload/UploadRecordReader.php` + impl under `app/Modules/Upload/` + bind in `UploadServiceProvider`
- Modify (add explicit `use App\Modules\Upload\Models\UploadRecord`): `app/Models/User.php` (~350), `app/Models/DeferCase.php` (~82), `app/Models/StudentActionAttachment.php` (~24), `app/Models/IeltsCertificate.php` (~50), `app/Models/StudentDecision.php` (~34), `app/Models/StudentActionLog.php` (~113)
- Modify (namespace swap): `app/Models/Answer.php`, `tests/Feature/Form/AdminQueryInboxTest.php`, `tests/Feature/Platform/SystemConfigurationMigrationTest.php`, `tests/Feature/Academic/StudentLifecycleTimelineQueryTest.php`, `tests/Feature/Form/QueryTicketWorkflowTest.php`, `tests/Feature/Api/V1/Student/QueryTicketApiTest.php`, `tests/Feature/Upload/UploadPlatformTest.php`
- Delete: `app/Models/UploadRecord.php`
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` — shrink lists
- Modify: `tests/Feature/Architecture/UploadModelPlacementArchTest.php` (~:34-39 asserts shim exists) — flip UploadRecord entry per its own ApplicationDocumentType pattern (:46-53)

## Implementation Steps

1. **Presence tests FIRST** (silent-failure guard): assert `attachment` key present-and-populated on a reply with a file (`QueryReplyResource` path) and response attachments non-empty (`QueryTicketResource` path). Add a **denial test**: non-owner student cannot resolve another reply's attachment via the new read path.
2. Build `UploadRecordReader` (byReplyIds/byResponseIds, keyed maps) + impl + bind.
3. Reroute the 6 consumer sites; rewrite both Resources off `relationLoaded`.
4. Drop relations + imports in the 2 Engagement models.
5. Add 6 explicit `use` statements in `app/Models`; namespace-swap `Answer.php` + 6 test files.
6. Confirm zero importers by BOTH detectors (string grep + bare `UploadRecord::` inside `namespace App\Models` files).
7. Delete shim; shrink arch lists; flip Upload placement test entry — same commit.
8. Run: Form + Upload + Api/V1/Student dirs; `tests/Feature/Academic/StudentLifecycleTimelineQueryTest.php` individually (Academic dir aborts on known CHECK-constraint flake); admin query-detail tests; `tests/Feature/Architecture`.

## Success Criteria

- [ ] Presence tests green BEFORE and AFTER refactor (attachment keys never silently vanish)
- [ ] Denial test green (no id-enumeration read path; `UploadRecordPolicy` semantics preserved by FK-keyed contract)
- [ ] Shim deleted; `SHIMMED_MODELS` = [ApplicationDocument]
- [ ] No `App\Modules\Upload\Models\*` import inside `app/Modules/Engagement/`
- [ ] Timeline/wallet/defer relations in `app/Models` resolve (lifecycle timeline test green)

## Rollback

Restore shim + revert arch/placement edits together; contract + reroutes can stay (they work with or without the shim). Serialized with phases 2/3/5 on `DeprecatedModelShimArchTest`.

## Risk Assessment

Highest-effort phase (6 consumer sites + 2 resources + contract). Two silent-failure modes neutralized by step-1 presence tests. N+1 guarded by batch keyed-map contract shape.
