---
title: "Bulk EGC Placement Import"
description: "Bulk-place students into EGC levels from a CSV list, creating the same action log/progression event as the manual classify dialog."
status: done
priority: P1
effort: "1-1.5d"
tags: [academic, progression, placement, import]
created: 2026-08-17
---

# Bulk EGC Placement Import

## Overview

Staff have a CSV export (`student_egc.csv`, 405 rows: `Full name, Student ID, Email,
University, Type, Level, Class, CP, Tuition discount, Note, HQ note, GC CẦN CHECK`)
of students who already have an approved application / `Student` record and need to
be placed into an EGC level in bulk, instead of one-by-one through
`ClassifyStudentDialog.vue` on the Placement Worklist page.

This plan adds a "Bulk Import" action to the existing **Placement Worklist**
page (`/students-placement-worklist`, Academic/Progression module — confirmed
as the correct owner over `/student-applications`, which is Admissions'
pending-application list, not an EGC-placement screen). The import reuses the
existing `InitializeStudentPlacementAction` (has_ielts=false path) so every
placed row gets identical side effects to the manual dialog: `ProgramEnrollment`
stage/level update, `StudentActionLog` (STUDENT_ENROLLMENT_NE), and
`AcademicProgressionEvent` (PLACEMENT_INITIALIZED).

Mirrors the existing preview → cache-token → execute Excel-import pattern
already used by `StudentActionAuditController` (`reports/student-actions/import/*`)
so the UI/UX and validation shape are consistent with what staff already know.

## Business Rules (confirmed with user)

- Only two columns matter: `Student ID` and `Level`. All other CSV columns are
  ignored.
- Level mapping: `Foundation` → 0, `EGC1` → 1, `EGC2` → 2, `EGC3` → 3, `EGC4` → 4,
  `EGC5` → 5 (case-insensitive, trimmed).
- Skip (do not place), and report in a per-row result table:
  - `Student ID` blank, or no matching `Student` found by `students.student_id`.
  - `Level` blank.
  - `Level` starts with `GCS` (e.g. `GCS5`) — explicitly excluded by the user.
  - `Level` is anything else unmapped (`EGC6`, `x`, or unrecognized text) —
    `EGC6` is also a hard system limit: `InitializePlacementRequest::english_level`
    validates `max:5`, so level 6 cannot be placed via this action regardless.
  - Student already placed (`ProgramEnrollment.study_stage` set, or a
    `PLACEMENT_INITIALIZED` progression event already exists) — surfaces as a
    row-level `InvalidProgressionState` error, not a hard failure of the batch.
- Target semester for `from_semester_id` / placement `semester_id`: resolved
  server-side from the same CRM intake mapping config used at
  `/student-applications/crm-mappings` (`CrmMappingSettings::getIntakeCode()`
  → `Semester` lookup by code), not user-entered per import. If unset, the
  import must fail fast with a clear error (mirrors
  `GetApplicationConversionReadinessQuery`'s existing "cohort not configured"
  guard pattern).
- Actor (`created_by_user_id` / `changed_by_user_id`): the logged-in staff
  member running the import via the UI (`$request->user()->id`), not a
  separate input.
- Runs wherever staff actually opens the page (dev now, prod later) — no
  environment-specific code path.

## Architecture

- **Module ownership**: entire feature (mapper, action, controller, requests,
  routes) lives in `App\Modules\Academic\Progression\*` — same owner as
  `AcademicPlacementController` / `InitializeStudentPlacementAction` /
  `StudentPlacementWorklistController`.
- **Cross-module read**: Academic needs the Admissions-owned CRM intake
  semester code. Add a small `Shared\Contracts\Admissions` reader interface
  (mirrors the existing `ApplicationProgramMappingReader` pattern — Admissions
  currently has zero inbound dependents from Academic, so this is the first,
  and the established way to add one) instead of Academic reaching into
  `App\Modules\Admissions\Support\Crm\CrmMappingSettings` directly.
- **Row processing**: new `BulkEgcPlacementRowMapper` (validates headers by
  column *name* lookup, not fixed position, since the source file has 13
  columns and only 2 matter) + new `ImportBulkEgcPlacementFromCsvAction`
  (preview/execute, calls existing `InitializeStudentPlacementAction::run()`
  per valid row inside its own try/catch so one bad row does not abort the
  batch — same resilience shape as `ImportStudentActionsFromExcelAction`).
- **Frontend**: new `BulkImportEgcPlacementDialog.vue` under
  `resources/js/Pages/Academic/PlacementWorklist/`, styled after
  `StudentActionsImport.vue` (upload → preview table → execute → summary),
  triggered from a new "Bulk Import" button on
  `resources/js/Pages/Academic/PlacementWorklist/Index.vue`.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Map CSV `Student ID`/`Level` rows to placement payloads with the confirmed skip rules | P1 |
| 2 | Reuse `InitializeStudentPlacementAction` per valid row via preview/execute endpoints | P1 |
| 3 | Resolve placement semester from the CRM intake mapping config, not user input | P1 |
| 4 | Give staff a preview table (valid/skip/error, with reasons) before committing | P1 |
| 5 | Wire a "Bulk Import" entry point into the Placement Worklist page | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Intake semester contract](./phase-01-start.md) | Done |
| 2 | [Phase 2: CSV mapper and intake semester contract](./phase-02-csv-mapper-and-intake-semester-contract.md) | Done |
| 3 | [Phase 3: Import action, controller, routes](./phase-03-import-action-controller-routes.md) | Done |
| 4 | [Phase 4: Frontend bulk import dialog](./phase-04-frontend-bulk-import-dialog.md) | Done |
| 5 | [Phase 5: Tests](./phase-05-tests.md) | Done |

## Success Criteria

- [~] Uploading `student_egc.csv` in preview mode shows correct counts: ~34
      `EGC6` skipped, ~219 `GCS*` skipped, ~2 `x` skipped, ~7 blank-Level
      skipped, plus any `Student ID` not found, with the remainder mapped to
      the right level 0-5. **Unverified**: the real `student_egc.csv` file
      isn't in this repo, so this was verified with an equivalent synthetic
      CSV instead (see Phase 5 tests) covering every skip-reason code and the
      valid-mapping path. Re-verify against the real file on first staff use.
- [x] Execute places only the previewed valid rows, each producing one
      `ProgramEnrollment` update, one `StudentActionLog`, and one
      `AcademicProgressionEvent`, identical in shape to using
      `ClassifyStudentDialog.vue` manually.
- [x] Re-running the same file skips already-placed students with a clear
      per-row reason instead of erroring the whole batch.
- [x] `can:change_student_status` gates every new route, matching the
      existing placement/classify and student-action-import routes.

## Open Questions

None — semester source, actor, GCS/EGC6/blank handling, and UI location were
all confirmed with the user during planning.

## Validation Log

### Verification Results (Session 1, 2026-08-17)
- Claims checked: 22
- Verified: 18 | Failed: 1 | Unverified: 3
- Tier: Full (5 phases → all 4 verification roles)

#### Failures
1. [Fact Checker/Contract Verifier] Phase 5 assumed PHPUnit-class test
   files; this repo's actual sibling suite
   (`tests/Feature/Academic/Progression/PlacementWorklistTest.php`,
   `PlacementOwnershipTest.php`) is Pest, flat-function style, with reusable
   `grantChangeStudentStatus()`/`makeWorklistStudent()` fixture helpers.
   **Resolved**: Phase 5 rewritten to Pest style, explicitly reusing those
   two helpers.

### Interview (Session 1)
1. **Contract namespace** — `IntakeSemesterReader` under
   `Shared\Contracts\Academic` (as originally drafted) or
   `Shared\Contracts\Admissions` (matches the sibling
   `ApplicationProgramMappingReader` convention, which is named after the
   *implementing* module)? → **`Shared\Contracts\Admissions`** (Recommended).
   Propagated to Phase 1 (interface definition + file path) and Phase 3
   (constructor type-hint).
2. **CSV mime rule** — keep the widened `mimes:csv,txt,xlsx,xls`, or add
   manual extension validation instead of relying on `mimes:`? → **Keep
   `mimes:csv,txt,xlsx,xls`** (Recommended). No plan change needed (Phase 3
   already specified this).
3. **Ziggy route registration step** — drop the "add route names to
   `resources/js/utils/routes` if needed" step from Phase 4 (verification
   found `ziggy-js` auto-registers all named Laravel routes)? → **Drop it**
   (Recommended). Propagated to Phase 4 (step removed, renumbered).

### Whole-Plan Consistency Sweep
- Files reread: plan.md, phase-01-start.md, phase-02-csv-mapper-and-intake-semester-contract.md, phase-03-import-action-controller-routes.md, phase-04-frontend-bulk-import-dialog.md, phase-05-tests.md
- Decision deltas checked: 3 (contract namespace, mime rule confirmed-as-is, ziggy step removal) + 1 verification fix (Phase 5 test framework)
- Reconciled stale references: 1 (`Shared\Contracts\Academic` → `Shared\Contracts\Admissions`, updated in Phase 1 and Phase 3; confirmed zero remaining stale references via repo-wide grep across the plan directory)
- Unresolved contradictions: 0

### Implementation Session (2026-08-17)

All 5 phases implemented and shipped in one session. 3 new backend files
(mapper, action, controller) + 2 requests + 1 contract + 1 impl + provider
binding + route wiring; 1 new Vue dialog + Index.vue wiring; 3 new test files
(15 tests, 78 assertions covering mapper/contract/feature paths).

Mandatory `code-reviewer` pass found 1 HIGH + 3 MEDIUM + 2 LOW real issues,
all fixed before finalize:
- **HIGH**: `BulkEgcPlacementRowMapper` looked up students with no campus
  filter — an operator could paste another campus's Student IDs. Fixed:
  `mapRow()` now takes `$campusId` and scopes the lookup; the action reads it
  from `session('current_campus_id')` (route already requires
  `campus.selected`). New unit test covers cross-campus exclusion.
- **MEDIUM**: preview `row_number` was off by one (copied from the sibling
  Excel importer's own off-by-one, not reproduced here since this is new
  code). Fixed `+2` → `+1` to match the real CSV line number.
- **MEDIUM**: `BulkImportEgcPlacementDialog.vue`'s `handleExecute()` didn't
  check `global_errors`, so an unconfigured-intake execute toasted "success"
  under a contradictory error alert. Fixed to mirror `handlePreview()`; test
  rewritten to hit the real semester-guard path (valid token, unconfigured
  semester) instead of the token-mismatch 422 it was accidentally asserting.
- **LOW**: swapped `ApiResponse::compatible()` for `success()`/`error()` —
  this is a greenfield endpoint with no legacy envelope to preserve.
- **LOW**: `mapLevel()` now strips NBSP (`\x{00A0}`) alongside regular
  whitespace, since spreadsheet exports carry it routinely.

**Not changed** (reviewer flagged but decision already verified with user):
the skip-reason priority (`student_id_missing`/`student_not_found` checked
*before* `level_excluded_gcs`/`level_unmapped`) — this exact order is the
plan's own confirmed Business Rule, not an oversight. The reviewer's
performance concern (~260 wasted point-queries on GCS/EGC6 rows before
optimization) is real but was assessed against the plan's business-rule
ordering, not a good enough reason to reverse it — the CSV is ≤1000 rows on
an indexed column, run rarely by staff.

**Not changed** (accepted plan deviation, informational only): the frontend
dialog uses a raw `&lt;Input type="file"&gt;` instead of reusing
`FileUpload.vue` per Phase 4's non-functional requirement — simpler for this
single-file, non-drag-drop use case; noted, not fixed.

**Informational, out of scope for this feature**: reviewer found the test
suite has pre-existing duplicate top-level Pest helper functions
(`rosterStudent`, `makeWorklistStudent`) across unrelated file pairs that
crash a combined run with exit 255/zero output — same class of bug this
session hit and fixed locally by renaming this feature's copies
(`grantChangeStudentStatusForBulkEgcImport`, `makeBulkEgcWorklistStudent`).
Worth a follow-up cleanup pass across the whole `tests/` tree; not touched
here to keep this diff scoped to the plan.

Full verification: 15 new tests pass in isolation; 77 tests pass across
`tests/Feature/Academic/Progression` + `tests/Unit/Admissions` together
(zero regressions); `pint --test` and `eslint` clean on all touched files;
`route:list --name=bulk-import` confirms both routes registered once, inside
the correct middleware group.

<!-- slug: bulk-egc-placement-import -->
