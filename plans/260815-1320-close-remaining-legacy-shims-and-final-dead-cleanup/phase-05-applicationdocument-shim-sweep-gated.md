---
phase: 5
title: "ApplicationDocument shim sweep (gated on fraud-design review)"
status: completed
priority: P2
effort: "1-2d incl. review"
dependencies: [1]
---

# Phase 5: ApplicationDocument shim sweep (gated)

## Overview

Last shim (owner: Upload). Pinned by a cross-module WRITE on the fraud-sensitive CRM ingest path. Red-team corrected the caller map: the global controller/services the original plan listed are DEAD and die in Phase 1. The LIVE write path has TWO callers:
1. `app/Modules/Admissions/Http/Api/IngestionController.php` (mounted via `AdmissionsServiceProvider:22`, guarded by allowlist + ability + throttle) → `UpsertCrmApplicationAction` (push shape)
2. `app/Modules/Admissions/Services/CrmApplicationSyncService.php:92-106` (NE nightly sync — ONLY producer of the `source => 'ne'` shape) → same action. NOTE: it catches `Throwable` and just counts `failed` + `Log::error` — a refactor breakage here fails **silently** in a log nobody reads.

## Requirements

- Functional: CRM ingest write semantics identical **except defects the gate explicitly enumerates and decides to fix** — do NOT freeze known defects into characterization tests.
- Non-functional: gate decision recorded (short ADR in `docs/adr/`) before any code change.

## Decided fix (validation Q1 — no longer a gate question)

Push shape currently keys `updateOrCreate` on `crm_file_id` ALONE (`UpsertCrmApplicationAction:213-225`; column globally unique per `2026_06_27_000005_create_application_documents_table.php:33`) — a payload for application B carrying application A's `crm_file_id` silently re-points A's document row to B. **DECIDED: fix it** — scope the push match key by `(crm_file_id, student_application_id)` exactly like the NE shape already does (`:245-252`), and land a mandatory negative test: a push payload cannot re-point another application's document. Characterization tests pin the FIXED behavior, not the defect.

## Gate (runs in PARALLEL with phases 1-4 — investigation + ADR only, no code; output = short ADR)

1. Field-overwrite matrix for both shapes (push vs NE) — what updateOrCreate touches per shape.
2. Where the write belongs: (a) Upload-owned public Action invoked by Admissions (Mức-3 default), vs (b) Admissions keeps orchestration, Upload exposes a narrow shape-explicit writer contract. Pick per least-behavior-drift. If logic moves out of any `frozen_services`/pinned path, update `config/migration_debt_paths.php` in the same commit.
3. Reconciliation (delete-not-in-set) ownership under the chosen shape; explicit ruling on the NE-empty-documents delete-all edge (`:255-259` — empty `documents` wipes all `ne:` rows for the application; investigate real NE CRM behavior, then rule; validation Q2).
4. **Remaining security ruling:** scheme allowlist for `documents.*.link` (currently free string, max 2048)? If yes → fix + test here; if no → record why.

## Related Code Files

- Modify: `app/Modules/Admissions/Actions/UpsertCrmApplicationAction.php` (live write, both shapes)
- Modify: `app/Modules/Admissions/Services/CrmApplicationSyncService.php` (NE caller)
- Modify: `app/Modules/Admissions/Http/Api/IngestionController.php` (live controller) — if it imports the legacy namespace
- Modify (add explicit `use App\Modules\Upload\Models\ApplicationDocument`): `app/Models/StudentApplication.php` (~148 — binds bare name)
- Create (per gate outcome): Upload-owned Action or writer contract + bind
- Modify (namespace swap): `tests/Feature/StudentApplication/{IndexCampusScopeTest,ExportTest,DocumentsTest}.php`, `tests/Feature/Admissions/{ApplicationBackfillTest,IngestionApplicationsTest}.php`
- Modify: `app/Services/Admissions/ApplicationBackfillService.php` — namespace swap (live via `BackfillApplicationsCommand`; pinned path — modify in place only)
- Delete: `app/Models/ApplicationDocument.php`
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` — final shrink (see completion state below)
- Modify: `tests/Feature/Architecture/UploadModelPlacementArchTest.php` — flip ApplicationDocument entry

## Implementation Steps

1. Run the gate review; write the ADR (including ruling on the push-key defect and NE-empty edge).
2. Characterization tests pinning the matrix BEFORE refactor, for BOTH callers: push shape, NE shape, push-empty-documents (no-op guard `:196-198`), NE-empty-documents (per gate ruling), NE `student_code`-only resolution. Plus the MANDATORY negative test (decided fix): a push payload cannot re-point another application's document.
3. Implement chosen shape; sweep remaining importers; add the `StudentApplication.php` explicit `use`.
4. Confirm zero importers by BOTH detectors; delete shim; final arch shrink + placement flip — same commit.
5. Run: `tests/Feature/Admissions/` (INCLUDING `CrmApplicationSyncTest.php`, `CrmApplicationMapperTest.php`), `tests/Feature/StudentApplication/`, Upload suite, `tests/Feature/Architecture`.

## Completion state (corrected — Goal 2)

`SHIMMED_MODELS` = []. `SHIMMED_MODEL_IMPORT_BASELINE` shrinks to its PERMANENT floor — the 2 morph backfill migrations **plus 3 assertion-data test files** that intentionally contain `App\Models\<X>` strings as negative-list/fixture data and must never be removed:
- `tests/Feature/Architecture/EngagementQueryTicketModelPlacementArchTest.php`
- `tests/Feature/Engagement/ClubMemberShimMorphBackfillMigrationTest.php`
- `tests/Feature/Architecture/FacilitiesDeliveryBoundaryArchTest.php`

Reaching 5 baseline entries = sweep 260811-0012 COMPLETE. Do NOT delete those assertions to chase an empty list.

## Success Criteria

- [ ] Gate ADR recorded before code change, incl. ruling on NE-empty delete-all + link scheme allowlist
- [ ] Push match key scoped by `(crm_file_id, student_application_id)` + negative test green (decided fix, validation Q1)
- [ ] Characterization tests (both callers, 5 cases) green before AND after refactor
- [ ] `SHIMMED_MODELS` = []; baseline = 5 permanent entries — sweep COMPLETE
- [ ] Admissions ingest + NE sync + backfill tests green (CrmApplicationSyncTest in run)

## Rollback

Restore shim + revert arch/placement edits together. Serialized with phases 2-4 on `DeprecatedModelShimArchTest`. If gate stalls: ship phases 1-4; plan closes 5/6 shims and the arch test documents the remainder.

## Risk Assessment

Highest-stakes phase: fraud-sensitive live write with a silent-failure caller (NE sync swallows Throwable). Mitigations: characterization-first, both callers test-pinned, gate rules on known defects instead of freezing them.
