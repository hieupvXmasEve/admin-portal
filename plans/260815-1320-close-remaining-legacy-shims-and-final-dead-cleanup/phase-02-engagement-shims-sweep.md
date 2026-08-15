---
phase: 2
title: "Engagement shims sweep (FormResponse, QueryTicket, QueryReply)"
status: completed
priority: P1
effort: "1d"
dependencies: [1]
---

# Phase 2: Engagement shims sweep

## Overview

Delete the 3 Engagement shims. Two blocker classes: (a) `Upload\UploadRecord` imports all three via legacy namespace for inverse relations; (b) 4 files inside `namespace App\Models` bind `FormResponse::class` UNQUALIFIED — no import, invisible to string grep (the exact failure that broke ClassSession→Room at runtime; see arch test docblock lines 173-186).

## Requirements

- Functional: student form/survey flows (`Student::formResponses()` etc.) unchanged; behavior identical.
- Non-functional: no new Engagement import inside Upload module; `cross_context_concrete_imports` stays at 0.

## Architecture

- **Unqualified same-namespace bindings** (bare `X::class` inside `namespace App\Models`): add explicit `use App\Modules\Engagement\Models\<X>;` — one line per file, relation bodies untouched.
- **Upload→Engagement inverse relations** on `UploadRecord` (`queryReply()`, `response()`, `ticket()`): believed unused, but names collide with ~15 other relations (`QueryTicket::response`, campus-scope filter `QueryTicketWorkflow:45`), so name-grep is unfalsifiable. Verify with a MODEL-ANCHORED inventory (step 1) and record the result in this file before deleting. If a real consumer surfaces → contract per `CourseSurveyTargetReader` precedent, never an in-place rename.
- Test files: plain namespace swap (tests exempt from cross-context rule).
- **Morph gate:** proceed only if Phase 1 pre-flight found zero `App\Models\{FormResponse,QueryTicket,QueryReply}` rows in `*_type` columns; else backfill migration first.

## Related Code Files

- Modify (add explicit `use App\Modules\Engagement\Models\FormResponse`): `app/Models/Student.php` (~line 330), `app/Models/StudentFormAssignment.php` (~40), `app/Models/StudentFormSurvey.php` (~47), `app/Models/Answer.php` (~35)
- Modify: `app/Modules/Upload/Models/UploadRecord.php` — remove `use App\Models\{FormResponse,QueryReply,QueryTicket}` + relations `queryReply()`, `response()`, `ticket()` (after step-1 inventory confirms zero consumers, all three including `response()`)
- Modify (namespace swap): `tests/Feature/Lecture/LecturerGpaReportTest.php`, `tests/Feature/Form/QueryTicketWorkflowTest.php`, `tests/Feature/Form/SurveyAggregateConfigTest.php`, `tests/Feature/Form/SurveyResultDownloadTest.php`, `tests/Feature/Form/AdminQueryInboxTest.php`, `tests/Feature/Api/V1/Student/EngagementFormsApiTest.php`, `tests/Feature/Api/V1/Student/QueryTicketApiTest.php`
- Delete: `app/Models/FormResponse.php`, `app/Models/QueryTicket.php`, `app/Models/QueryReply.php`
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` — remove 3 from `SHIMMED_MODELS`, remove swept files from `SHIMMED_MODEL_IMPORT_BASELINE` (`ALL_MIGRATED_MODELS` never shrinks)
- Modify: `tests/Feature/Architecture/EngagementFormModelPlacementArchTest.php` (blockedModels line ~65-69) + `tests/Feature/Architecture/EngagementQueryTicketModelPlacementArchTest.php` (~47-51) — both currently assert the shims EXIST; flip to shim-deleted per the `ApplicationDocumentType` pattern in `UploadModelPlacementArchTest.php:46-53`, and update their now-false comments

## Implementation Steps

1. Model-anchored consumer inventory for the 3 inverse relations: grep call sites where receiver is an `UploadRecord` (typehints, `UploadRecord::query()` chains, `$upload->`-style vars) and eager-load strings `with('queryReply'|'response'|'ticket')` / `load(...)` on Upload-rooted queries only. Record the command + result here.
2. Add the 4 explicit `use` statements in `app/Models`.
3. Delete relations + imports in `UploadRecord.php`.
4. Namespace-swap 7 test files.
5. Confirm zero importers by BOTH detectors: string grep `App\\Models\\(FormResponse|QueryTicket|QueryReply)\b` AND bare-name grep `\b(FormResponse|QueryTicket|QueryReply)::` restricted to files declaring `namespace App\Models`.
6. Delete 3 shims; shrink DeprecatedModelShimArchTest lists; flip 2 placement arch tests — same commit.
7. Run: Engagement + Upload + Form + Api/V1/Student dirs, then `tests/Feature/Architecture`.

## Success Criteria

- [ ] Step-1 inventory result recorded (zero consumers confirmed or contract added)
- [ ] 3 shims deleted; `SHIMMED_MODELS` = [ApplicationDocument, GoldTransaction, UploadRecord]
- [ ] Arch tests green (incl. both flipped placement tests); Form/Engagement/Upload/Student-API: no new failures
- [ ] Student form/survey smoke: `Student::formResponses()` path exercised by existing Form tests passes

## Rollback

Restore 3 shim files + revert the two arch-test edits + placement-test flips in one revert. Serialized with phases 3-5 on `DeprecatedModelShimArchTest` (exact-match constants — no parallel PRs).

## Risk Assessment

Main residual: a consumer of the inverse relations hidden behind dynamic relation strings; step-1 anchored inventory + step-7 suites cover. Morph risk moved to Phase 1 pre-flight (do NOT assume the 2 existing backfill migrations cover these models — they don't).
