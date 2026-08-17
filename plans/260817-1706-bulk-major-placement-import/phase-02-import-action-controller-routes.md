---
phase: 2
title: "Import action, controller, routes"
status: pending
priority: P1
effort: "4h"
dependencies: [1]
---

# Phase 2: Import action, controller, routes

## Overview

Wire the mapper into a preview/execute action that calls
`InitializeStudentPlacementAction::run()` with `has_ielts: true` per valid
row, plus the controller + FormRequests + routes exposing it. Copies the
shape of `ImportBulkEgcPlacementFromCsvAction` /
`BulkEgcPlacementImportController` from the sibling plan almost exactly —
same preview/execute split, same cache-backed token, same per-row
try/catch resilience.

## Requirements

- Functional:
  - Preview: parse file, map every row via Phase 1's mapper, return counts +
    per-row detail (including `overall_score`), without writing anything.
    <!-- Updated: Validation Session 1 - dropped predicted_outcome; every
    valid row is unconditionally Major-bound, below-threshold rows are a
    skip reason (below_threshold_excluded), not a predicted-fallback state. -->
  - Execute: re-validate the file hash against the cached preview token
    (same anti-tamper check as the EGC importer), then call
    `InitializeStudentPlacementAction::run()` for each `valid` row with
    `has_ielts: true`.
  - Semester resolution: reuse the existing
    `App\Shared\Contracts\Admissions\IntakeSemesterReader` contract — same
    dependency the EGC importer already uses, no new contract needed here.
    If unconfigured, both preview and execute return a hard `global_errors`
    entry, no placement attempted (same behavior as the EGC importer).
  - A row that throws `InvalidProgressionState` (already placed) or any
    other `\Throwable` during execute is recorded as `status: 'error'` with
    a safe message, and does **not** abort the remaining rows.
- Non-functional: reuse `ApiResponse::success()`/`ApiResponse::error()` in
  the controller — **not** `ApiResponse::compatible()` (that method is
  reserved for legacy-envelope migrations per its own docblock; these are
  greenfield routes, same lesson the EGC importer's code review already
  established). Max rows cap `MAX_IMPORT_ROWS = 1000`, same as the EGC
  importer. Gated by `can:change_student_status` on every route.

## Related Code Files

- Create: `app/Modules/Academic/Progression/Actions/Placement/ImportBulkMajorPlacementFromCsvAction.php`
- Create: `app/Modules/Academic/Progression/Http/Web/BulkMajorPlacementImportController.php`
- Create: `app/Modules/Academic/Http/Requests/PreviewBulkMajorPlacementImportRequest.php`
- Create: `app/Modules/Academic/Http/Requests/ExecuteBulkMajorPlacementImportRequest.php`
- Modify: `app/Modules/Academic/routes/web.php`
- Read (reference, no changes): `app/Modules/Academic/Progression/Actions/Placement/ImportBulkEgcPlacementFromCsvAction.php`
- Read (reference, no changes): `app/Modules/Academic/Progression/Http/Web/BulkEgcPlacementImportController.php`

## Implementation Steps

1. `ImportBulkMajorPlacementFromCsvAction`:
   - Constructor: `BulkMajorPlacementRowMapper $mapper`,
     `App\Shared\Contracts\Admissions\IntakeSemesterReader $intakeSemester`.
   - `preview(UploadedFile $file): array` and
     `execute(UploadedFile $file, int $userId): array`, same
     `process($file, $shouldExecute, $userId)` internal split as the EGC
     importer.
   - Read sheet via `Maatwebsite\Excel\Facades\Excel::toArray()` — same call
     already proven for both `.csv` and `.xlsx` in this codebase's import
     paths.
   - `$campusId = (int) session('current_campus_id')`, passed into every
     `mapper->mapRow()` call.
   - `$rowNumber = (int) $index + 1` (not `+2` — the EGC importer shipped
     with this off-by-one and it was left as a known, unfixed sibling bug;
     do not copy it into new code).
   - Resolve `$semesterId` once via `$this->intakeSemester->currentIntakeSemesterId()`;
     if `null`, short-circuit with
     `global_errors: ['Intake semester is not configured. Set it at /student-applications/crm-mappings first.']`.
   - For each mapped `valid` row during execute:
     ```php
     try {
         InitializeStudentPlacementAction::run([
             'student_id' => $mapped['normalized_payload']['student_id'],
             'semester_id' => $semesterId,
             'has_ielts' => true,
             'ielts_score' => $mapped['normalized_payload']['ielts_score'],
             'issue_date' => $mapped['normalized_payload']['issue_date'],
             'missing_documents' => true,
             'notes' => "Bulk Major import ({$file->getClientOriginalName()}), score sourced from application overall.",
             'created_by_user_id' => $userId,
         ]);
         $success++;
     } catch (InvalidProgressionState $e) {
         $row['status'] = 'error';
         $row['error'] = $e->getMessage();
         $failed++;
     } catch (\Throwable $e) {
         Log::error('Bulk Major placement import row failed', ['row_number' => $rowNumber, 'error' => $e->getMessage()]);
         $row['status'] = 'error';
         $row['error'] = 'Import failed on this row due to a business validation error.';
         $failed++;
     }
     ```
   - Return shape mirrors the EGC importer's
     `['summary' => [...], 'header' => [...], 'rows' => [...], 'global_errors' => [...]]`
     with `summary.skip_counts` keyed by Phase 1's 4 skip-reason codes
     (`student_id_missing`, `student_not_found`, `application_score_missing`,
     `below_threshold_excluded`). <!-- Updated: Validation Session 1 - was
     3 skip codes + predicted_major/predicted_egc_fallback summary counts;
     now 4 skip codes, no predicted-outcome summary needed since every
     valid row is unconditionally Major-bound. -->
   - Every row this loop sends to `InitializeStudentPlacementAction::run()`
     already has `ielts_score >= 5.5` (Phase 1 guarantees it), so the
     action's own EGC-fallback branch is structurally unreachable through
     this importer — it remains a safety net inside the action itself, not
     something this action needs to branch on or report separately.

2. `BulkMajorPlacementImportController`:
   - `previewImport(PreviewBulkMajorPlacementImportRequest $request, ImportBulkMajorPlacementFromCsvAction $action): JsonResponse`
     — `Cache::put('bulk-major-import-preview:{user}:{uuid}', ['file_hash' => ...], now()->addMinutes(10))`
     — distinct cache-key prefix from the EGC importer's
     `bulk-egc-import-preview:*`, no collision risk.
   - `executeImport(ExecuteBulkMajorPlacementImportRequest $request, ImportBulkMajorPlacementFromCsvAction $action): JsonResponse`
     — validate `preview_token` against the cache + file hash, then call
     `$action->execute(...)`, `Cache::forget($cacheKey)`.
   - Both return via `ApiResponse::success([...])` on success,
     `ApiResponse::error($message, status: 422)` on token mismatch.

3. FormRequests: `authorize(): true`; `file` rule
   `['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240']` (same widened
   mime rule as the EGC importer, confirmed in that plan's validation
   interview); `ExecuteBulkMajorPlacementImportRequest` additionally requires
   `preview_token`.

4. Routes in `app/Modules/Academic/routes/web.php`, inside the existing
   `Route::middleware(['auth', 'verified', 'campus.selected'])` group, next
   to the EGC importer's `bulk-import` route group:
   ```php
   Route::prefix('students-placement-worklist/bulk-import-major')
       ->name('students.placement-worklist.bulk-import-major.')
       ->group(function () {
           Route::post('/preview', [BulkMajorPlacementImportController::class, 'previewImport'])
               ->middleware('can:change_student_status')
               ->name('preview');
           Route::post('/execute', [BulkMajorPlacementImportController::class, 'executeImport'])
               ->middleware('can:change_student_status')
               ->name('execute');
       });
   ```

## Success Criteria

- [ ] Preview never writes to `students`, `program_enrollments`,
      `ielts_certificates`, `student_action_logs`, or
      `academic_progression_events`.
- [ ] Execute with an unconfigured intake mapping returns a clear error and
      creates zero rows (verify via a valid preview token, not just a
      token-mismatch 422 — the EGC importer's first test attempt at this
      accidentally tested the wrong guard; write it against the real
      semester-check path this time).
- [ ] Execute places only the rows Phase 1 classified as `valid`, skips the
      rest, and an already-placed student surfaces as `status: 'error'`
      without stopping the rest of the batch.
- [ ] Both routes 403 for a user without `change_student_status`.
- [ ] `bulk-major-import-preview:*` cache keys never collide with
      `bulk-egc-import-preview:*` or `student-action-import-preview:*`.

## Risk Assessment

- **Duplicate re-import**: same as the EGC importer — already-placed
  students throw `InvalidProgressionState`, surfaced as per-row errors, no
  extra dedup logic needed.
- **Score staleness between preview and execute**: if a student's
  application record changes between preview and execute (unlikely in the
  minutes-long window this cache token lives for), execute re-runs the
  mapper fresh rather than trusting preview's cached `overall_score` — this
  is correct behavior (always place on current data), just note it's
  possible for preview and execute to show a different predicted outcome
  in that edge case; not worth guarding against for a rarely-run admin tool.
