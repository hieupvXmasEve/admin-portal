---
title: "Bulk Major Placement Import"
description: "Bulk-place students into the Major (intake_course) pathway from a Student-ID-only CSV, sourcing the IELTS score from their existing application record instead of a CSV column."
status: done
priority: P1
effort: "1-1.5d"
tags: [academic, progression, placement, import, ielts]
created: 2026-08-17
---

# Bulk Major Placement Import

## Overview

Sibling feature to the already-shipped [Bulk EGC Placement Import](../260817-1515-bulk-egc-placement-import/plan.md)
(commit `274a3ad2e`). Staff have a list of Student IDs who should be placed
into the Major pathway. Unlike the EGC importer, this CSV carries **only
`Student ID`** — no Level/score column. The IELTS score is looked up
server-side from the student's own application record
(`student_applications.overall`), for accuracy (confirmed with user: "chỉ
cần danh sách có Student ID là được, còn lại sẽ lấy từ application cho chính
xác").

Reuses `InitializeStudentPlacementAction`'s existing `has_ielts=true` branch
unchanged — same write path the manual `ClassifyStudentDialog.vue` "Major
(IELTS)" radio already uses. That branch already self-corrects: if the
looked-up score is below the 5.5 threshold, the student lands in EGC
(`intake_pre_uni_gc`, level 0) instead of failing — same behavior as manual
entry.

## Business Rules (confirmed with user + verified against live data)

- CSV needs only a `Student ID` column (header-by-name lookup, like the EGC
  importer — other columns, if present, are ignored).
- Score source: `student_applications.overall`, **filtered to
  `english_test_type = 'IELTS'` exactly** — `overall` is a shared column also
  used for TOEFL/other tests (migration widened it to `decimal(5,2)`
  specifically to fit TOEFL's range; live data has exactly two distinct
  `english_test_type` values, `IELTS` and `Other` — verified via tinker
  against the running dev DB). Do not read `overall` from a non-IELTS
  application — the value would be on the wrong scale for the 5.5 threshold
  and would be recorded as if it were IELTS.
- Query per student: `StudentApplication::where('student_id', $student->id)
  ->where('english_test_type', 'IELTS')->whereNotNull('overall')
  ->orderByDesc('created_at')->first()` — the latest qualifying IELTS
  application wins when a student has more than one application.
- Skip (do not place), report per-row:
  - `Student ID` blank, or no matching `Student` found by
    `students.student_id`, **scoped to the current campus**
    (`session('current_campus_id')`) — same cross-campus leak already fixed
    in the EGC importer must not be reintroduced here.
  - No qualifying IELTS application with a non-null `overall` score found —
    `application_score_missing`.
  - **Score below the 5.5 threshold — `below_threshold_excluded`.**
    Confirmed with user during validation: this importer does **not** use
    `InitializeStudentPlacementAction`'s self-correcting EGC-fallback
    behavior. A below-threshold row is a preview-time **skip**, not a
    valid/execute-eligible row — staff review these separately (manually,
    or via the EGC importer) rather than having them silently land in EGC
    through a "Major" import. Only rows at or above 5.5 are ever executed
    by this importer.
  - Student already placed — surfaces as a row-level `InvalidProgressionState`
    error at execute time, same as the EGC importer (not a preview skip).
- Preview still shows the looked-up `overall_score` for every row that
  reaches the score check — including `below_threshold_excluded` rows, so
  staff can see *why* (score X < 5.5), not just that it was skipped.
- `english_level` is never supplied (no CSV column for it, and this
  importer never triggers the EGC-fallback branch, so it's never needed).
- `missing_documents = true` on every imported `IeltsCertificate` (no file
  upload during a headless import) — same convention `RecordIeltsCertificateAction`
  already uses for "record now, scan later."
- `issue_date` on the certificate is set from the application's own
  `exam_date` when present (free, already-fetched data — not a new lookup).
- Semester resolution, actor, and target environment: identical to the EGC
  importer (`IntakeSemesterReader` contract, `$request->user()->id`, no
  environment-specific code).

## Architecture

- **Module ownership**: same owner as the EGC importer —
  `App\Modules\Academic\Progression\*` (mapper, action, controller, requests)
  + `app/Modules/Academic/Http/Requests/*` (matches the EGC importer's actual
  request location, not `Progression/Http/Requests/`).
- **No new cross-module contract needed.** `App\Models\StudentApplication`
  is a legacy top-level model — `docs/module-architecture-map.md` §4
  documents "Tất cả module → Legacy `App\Models`: direct model import
  (shared kernel de-facto)" as an existing, allowed edge. Unlike the intake
  semester (which lived inside Admissions' module-owned `Support/` and
  needed `IntakeSemesterReader`), `StudentApplication` needs no wrapper.
- **Row processing**: new `BulkMajorPlacementRowMapper` — `Student ID`-only
  header lookup, campus-scoped student lookup, IELTS-application lookup,
  threshold check. <!-- Updated: Validation Session 1 - below-threshold rows
  are excluded (status=skip), not marked valid-with-a-predicted-fallback -->
  Only rows at/above the 5.5 threshold are `status: 'valid'`; every `valid`
  row is guaranteed to land in Major (`intake_course`) at execute time — this
  importer never triggers `InitializeStudentPlacementAction`'s EGC-fallback
  branch by design (confirmed with user; see Business Rules). New
  `ImportBulkMajorPlacementFromCsvAction` (preview/execute, calls
  `InitializeStudentPlacementAction::run()` with `has_ielts: true` per valid
  row inside its own try/catch, same per-row resilience as the EGC importer).
- **Frontend**: new `BulkImportMajorPlacementDialog.vue`, second trigger
  button next to the existing "Bulk Import" button on
  `resources/js/pages/Academic/PlacementWorklist/Index.vue`.
- **Carried-forward lessons from the EGC importer's code review** (apply
  from the start this time, not as a fix-up):
  - Campus-scope every student lookup.
  - `row_number` in the mapper loop is `(int) $index + 1` (array key from
    `array_slice($sheet, 1, null, true)` already equals the real 0-based
    data-row offset).
  - Controller responses use `ApiResponse::success()`/`ApiResponse::error()`
    — not `ApiResponse::compatible()` (that method's own docblock reserves
    it for legacy-envelope migrations; these are greenfield routes).
  - Frontend `handleExecute()` must check `result.global_errors` before
    toasting success (the EGC dialog's own bug, already fixed there —
    write it correctly here from the start).
  - Pest test files share one PHP global namespace — any file-scope
    `function` helper copied from `BulkEgcPlacementImportTest.php` or
    `PlacementWorklistTest.php` must be uniquely renamed
    (e.g. `grantChangeStudentStatusForBulkMajorImport`,
    `makeBulkMajorWorklistStudent`) or the whole suite crashes with exit
    255 and zero output when both files run together. Do not copy the
    plain names verbatim.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Map CSV `Student ID` rows to a placement payload sourced from the student's latest qualifying IELTS application | P1 |
| 2 | Reuse `InitializeStudentPlacementAction` (has_ielts=true) per valid row via preview/execute endpoints | P1 |
| 3 | Preview shows the looked-up score and excludes below-5.5 rows per row before committing | P1 |
| 4 | Wire a second "Bulk Import Major" entry point into the Placement Worklist page | P2 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Row mapper + application score lookup](./phase-01-start.md) | Done |
| 2 | [Phase 2: Import action, controller, routes](./phase-02-import-action-controller-routes.md) | Done |
| 3 | [Phase 3: Frontend bulk major import dialog](./phase-03-frontend-bulk-major-import-dialog.md) | Done |
| 4 | [Phase 4: Tests](./phase-04-tests.md) | Done |

## Success Criteria

- [x] Uploading a Student-ID-only CSV in preview mode shows, per row: the
      resolved student, the looked-up IELTS score, and its status — `valid`
      (score >= 5.5) or a specific skip reason including
      `below_threshold_excluded` when the score is present but under 5.5.
- [x] Execute places only the previewed valid (>=5.5) rows, each producing
      one `IeltsCertificate`, one `ProgramEnrollment` update (always
      `study_stage: intake_course`), one `StudentActionLog`, and both an
      `IELTS_RECORDED` and a `PLACEMENT_INITIALIZED` `AcademicProgressionEvent`
      — identical in shape to using `ClassifyStudentDialog.vue`'s Major radio
      manually with a qualifying score.
- [x] A row for a student with no qualifying IELTS application (missing,
      wrong test type, null score, or below-threshold score) is skipped in
      preview with a specific reason, never silently defaulted or
      auto-placed into EGC.
- [x] A row for an already-placed student surfaces as a row-level error at
      execute time without aborting the rest of the batch.
- [x] `can:change_student_status` gates both new routes.
- [x] Cross-campus student lookup is impossible (student search scoped to
      `session('current_campus_id')` from the first commit of this plan).

## Open Questions

None — CSV shape (Student-ID-only), score source (`student_applications.overall`
filtered to `english_test_type = 'IELTS'`), and threshold/exclusion behavior
were confirmed with the user and verified against live `english_test_type`
data during planning and validation.

## Validation Log

### Verification Results (Session 1, 2026-08-17)
- Tier: Standard (4 phases → Fact Checker + Contract Verifier)
- Claims checked: 6 | Verified: 6 | Failed: 0 | Unverified: 0
- `Student.campus_id`, `IeltsCertificate::SCORE_THRESHOLD_INTAKE_COURSE`
  (5.5), `student_applications.timestamps()` (has `created_at`),
  `student_applications.{english_test_type,overall}` column types,
  `InitializeStudentPlacementAction`'s `issue_date` param, and
  `IntakeSemesterReader`'s existing binding/usage all confirmed via direct
  grep/read against current source — see planning-session tool calls.

### Session 1 (2026-08-17)
**Trigger:** Standard `/ak:plan validate` after initial plan write.
**Questions asked:** 3

#### Questions & Answers

1. **[Risk/Assumptions]** A row with an IELTS score below 5.5 threshold —
   `InitializeStudentPlacementAction` auto-falls-back to EGC (level 0)
   rather than failing. Should the bulk Major importer let this happen
   automatically (self-correcting, same as manual entry), or skip/exclude
   those rows from this Major-specific import so staff review them
   separately?
   - Options: Auto-fallback to EGC (Recommended) | Skip/exclude from this import
   - **Answer:** Skip/exclude from this import
   - **Rationale:** A bulk "Major" import silently placing someone into EGC
     instead would be surprising at scale (unlike the manual dialog, where
     one staff member enters one score and sees the immediate result).
     Below-threshold rows are their own preview-time skip reason
     (`below_threshold_excluded`); only >=5.5 rows ever execute through this
     importer. This is a genuine scope change from the initial draft, not
     just a confirmation — propagated to Phase 1 (skip reason set, mapper
     no longer marks sub-threshold rows valid), Phase 2 (action always
     results in Major, EGC-fallback branch is unreachable through this
     importer by construction), Phase 3 (preview no longer needs an
     EGC-fallback badge state, simplify to skip-reason display), Phase 4
     (new test case for the exclusion, remove the fallback-branch
     assertion).

2. **[Architecture]** Should the Major importer live in a new
   `BulkMajorPlacementImportController`, or add
   `previewMajorImport`/`executeMajorImport` methods onto the existing
   `BulkEgcPlacementImportController`?
   - Options: Separate controller (Recommended) | Extend existing controller
   - **Answer:** Separate controller (Recommended)
   - **Rationale:** Matches the plan as drafted — no phase changes needed.

3. **[Architecture/UX]** Should this be a second "Bulk Import Major" button
   opening a separate dialog, or should the existing "Bulk Import" (EGC)
   dialog gain a pathway toggle (EGC vs Major) instead?
   - Options: Second separate button/dialog (Recommended) | Single dialog with pathway toggle
   - **Answer:** Second separate button/dialog (Recommended)
   - **Rationale:** Matches the plan as drafted — no phase changes needed.

#### Confirmed Decisions
- Below-threshold handling: exclude from this importer entirely (new skip
  reason `below_threshold_excluded`) — not auto-fallback.
- Controller: new `BulkMajorPlacementImportController`, not an extension of
  `BulkEgcPlacementImportController`.
- UI entry point: second standalone button + dialog, not a toggle on the
  existing EGC dialog.

#### Action Items
- [x] Propagate below-threshold exclusion decision to plan.md (Business
      Rules, Architecture, Goals, Success Criteria).
- [x] Propagate to Phase 1 (skip reason set, `mapRow` no longer returns
      `predicted_outcome: 'egc_fallback'` as a valid status).
- [x] Propagate to Phase 2 (action's execute loop always results in Major;
      note the EGC-fallback branch is unreachable through this importer).
- [x] Propagate to Phase 3 (preview table drops the EGC-fallback badge
      state, shows `below_threshold_excluded` as a skip reason instead).
- [x] Propagate to Phase 4 (add exclusion test case, drop the
      fallback-branch execute assertion).

#### Impact on Phases
- Phase 1: skip-reason set changes from 3 to 4 codes; `predicted_outcome`
  field removed (valid rows are unconditionally Major-bound; skip rows
  carry `overall_score` + `skip_reason` instead).
- Phase 2: execute loop simplified — no fallback-branch handling needed,
  `InitializeStudentPlacementAction`'s own fallback logic remains a defensive
  no-op safety net (never actually triggered by rows this importer sends).
- Phase 3: preview UI simplified — one fewer visual state to design for.
- Phase 4: one skip-reason test added, one fallback-outcome test removed.

### Implementation & Code Review (2026-08-17)
All 4 phases implemented, mirroring the shipped EGC importer 1:1 (mapper,
action, controller, requests, routes, dialog, Index.vue wiring). Tests: 42
pass together (new + EGC + PlacementWorklist + PlacementOwnership suites,
avoiding the pre-existing duplicate-Pest-helper landmine noted in phase-04).
Pint + eslint clean on all touched files.

`code-reviewer` subagent: APPROVE, no CRITICAL, all 11 acceptance criteria
verified against live code + dev DB. Findings addressed same session:
- **H2 (fixed)** — execute re-resolved scores from live data with only the
  file hash pinned, so a row could flip valid *after* preview and get
  silently placed — the exact behavior the below-threshold-exclusion
  decision was meant to prevent. Fixed: preview now caches its own
  `valid_student_ids` alongside `file_hash`; execute rejects (row-level
  error, not global) any row whose student isn't in that set.
- **M1 (fixed)** — added `score_out_of_range` skip reason (`overall > 9.0`)
  guarding against a wrong-scale value slipping through under
  `english_test_type = 'IELTS'`.
- **M2 (fixed)** — added `orderByDesc('id')` tie-break after `created_at` for
  same-timestamp applications.
- **M4 (fixed)** — added stale/invalid-token test, changed-since-preview
  test, and a `row_number` regression test.
- **L4 (fixed)** — dialog clears `previewToken` after a successful execute.
- **H1 (deferred)** — sync 1000-row batch can time out mid-request; same
  behavior as the shipped EGC sibling, not a regression. Left as a known
  limitation; lower `MAX_IMPORT_ROWS` or move to a queued job if real usage
  hits it.
- **M3 (deferred)** — 2 DB queries/row (~4k round trips at max batch size);
  batching is a bigger refactor, no correctness impact, deferred.
- L1/L2/L3/L5 — accepted as-is, same as the EGC sibling.

### Whole-Plan Consistency Sweep
- Files reread: plan.md, phase-01-start.md, phase-02-import-action-controller-routes.md, phase-03-frontend-bulk-major-import-dialog.md, phase-04-tests.md
- Decision deltas checked: 1 (below-threshold exclusion, cascading through skip-reason set, mapper output shape, action behavior description, preview UI, and test coverage)
- Reconciled stale references: 4 (phase-01, phase-02, phase-03, phase-04 — see per-file `<!-- Updated: Validation Session 1 -->` markers)
- Unresolved contradictions: 0

<!-- slug: bulk-major-placement-import -->
