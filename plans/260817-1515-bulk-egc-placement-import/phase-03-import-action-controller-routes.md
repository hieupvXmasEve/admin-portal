---
phase: 3
title: "Import action, controller, routes"
status: done
priority: P1
effort: "4h"
dependencies: [1, 2]
---

# Phase 3: Import action, controller, routes

## Overview

Wire the mapper into a preview/execute action that actually calls
`InitializeStudentPlacementAction::run()` per valid row, plus the
controller + FormRequests + routes exposing it. Copies the shape of
`ImportStudentActionsFromExcelAction` / `StudentActionAuditController`
(`previewImport`/`executeImport` with a cache-backed preview token).

## Requirements

- Functional:
  - Preview: parse file, map every row, return counts + per-row detail,
    without writing anything.
  - Execute: re-validate the file hash against the cached preview token
    (same anti-tamper check as the student-actions importer), then call
    `InitializeStudentPlacementAction::run()` for each `valid` row.
  - Semester resolution happens once per request via
    `IntakeSemesterReader::currentIntakeSemesterId()` (Phase 1). If `null`,
    both preview and execute return a hard error — no placement attempted.
  - A row that throws `InvalidProgressionState` (already placed) or any
    other `\Throwable` during execute is recorded as `status: 'error'` with
    the exception message, and does **not** abort the remaining rows
    (same resilience contract as `ImportStudentActionsFromExcelAction::process()`).
- Non-functional: max rows cap (reuse `MAX_IMPORT_ROWS = 1000` style guard),
  gated by `can:change_student_status` on every route.

## Related Code Files

- Create: `app/Modules/Academic/Progression/Actions/Placement/ImportBulkEgcPlacementFromCsvAction.php`
- Create: `app/Modules/Academic/Progression/Http/Web/BulkEgcPlacementImportController.php`
- Create: `app/Modules/Academic/Http/Requests/PreviewBulkEgcPlacementImportRequest.php`
- Create: `app/Modules/Academic/Http/Requests/ExecuteBulkEgcPlacementImportRequest.php`
- Modify: `app/Modules/Academic/routes/web.php`

## Implementation Steps

1. `ImportBulkEgcPlacementFromCsvAction`:
   - Constructor: `BulkEgcPlacementRowMapper $mapper`,
     `App\Shared\Contracts\Admissions\IntakeSemesterReader $intakeSemester`
     (Phase 1's contract — confirmed under `Shared\Contracts\Admissions`,
     not `Academic`).
   - `preview(UploadedFile $file): array` and
     `execute(UploadedFile $file, int $userId): array`, same
     `process($file, $shouldExecute)` internal split as
     `ImportStudentActionsFromExcelAction`.
   - Read sheet via `Maatwebsite\Excel\Facades\Excel::toArray()` (already
     proven to read `.csv` in this codebase's import path — same call used
     by `ImportStudentActionsFromExcelAction::getFirstSheet()`).
   - Resolve `$semesterId = $this->intakeSemester->currentIntakeSemesterId()`
     once; if `null`, short-circuit with
     `global_errors: ['Intake semester is not configured. Set it at /student-applications/crm-mappings first.']`.
   - For each mapped `valid` row during execute:
     ```php
     try {
         InitializeStudentPlacementAction::run([
             'student_id' => $mapped['normalized_payload']['student_id'],
             'semester_id' => $semesterId,
             'has_ielts' => false,
             'english_level' => $mapped['normalized_payload']['english_level'],
             'notes' => "Bulk EGC import ({$file->getClientOriginalName()})",
             'created_by_user_id' => $userId,
         ]);
         $success++;
     } catch (InvalidProgressionState $e) {
         $row['status'] = 'error';
         $row['error'] = $e->getMessage();
         $failed++;
     } catch (\Throwable $e) {
         Log::error('Bulk EGC placement import row failed', ['row_number' => $rowNumber, 'error' => $e->getMessage()]);
         $row['status'] = 'error';
         $row['error'] = 'Import failed on this row due to a business validation error.';
         $failed++;
     }
     ```
   - Return shape mirrors `ImportStudentActionsFromExcelAction`'s
     `['summary' => [...], 'header' => [...], 'rows' => [...], 'global_errors' => [...]]`
     so the frontend (Phase 4) can reuse the same rendering logic as
     `StudentActionsImport.vue`.

2. `BulkEgcPlacementImportController`:
   - `previewImport(PreviewBulkEgcPlacementImportRequest $request, ImportBulkEgcPlacementFromCsvAction $action): JsonResponse`
     — same `Cache::put('bulk-egc-import-preview:{user}:{uuid}', ['file_hash' => ...], now()->addMinutes(10))`
     pattern as `StudentActionAuditController::previewImport()`.
   - `executeImport(ExecuteBulkEgcPlacementImportRequest $request, ImportBulkEgcPlacementFromCsvAction $action): JsonResponse`
     — validate `preview_token` against the cache + file hash, then call
     `$action->execute(...)`, `Cache::forget($cacheKey)`.
   - Both return via `App\Http\Responses\ApiResponse::compatible([...])`,
     same as the student-actions importer.

3. FormRequests: `authorize(): true`; `file` rule
   `['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240']` (widened
   from the student-actions importer's `xlsx,xls` since the source file here
   is `.csv`); `ExecuteBulkEgcPlacementImportRequest` additionally requires
   `preview_token`.

4. Routes in `app/Modules/Academic/routes/web.php`, inside the existing
   `Route::middleware(['auth', 'verified', 'campus.selected'])` group, next
   to the `students-placement-worklist` route:
   ```php
   Route::prefix('students-placement-worklist/bulk-import')
       ->name('students.placement-worklist.bulk-import.')
       ->group(function () {
           Route::post('/preview', [BulkEgcPlacementImportController::class, 'previewImport'])
               ->middleware('can:change_student_status')
               ->name('preview');
           Route::post('/execute', [BulkEgcPlacementImportController::class, 'executeImport'])
               ->middleware('can:change_student_status')
               ->name('execute');
       });
   ```

## Success Criteria

- [x] Preview never writes to `students`, `program_enrollments`,
      `student_action_logs`, or `academic_progression_events`.
- [x] Execute with an unconfigured intake mapping returns a clear error and
      creates zero rows.
- [x] Execute on the real CSV places exactly the rows Phase 2 classified as
      `valid`, skips the rest, and a row for an already-placed student
      surfaces as `status: 'error'` without stopping the rest of the batch.
- [x] Both routes 403 for a user without `change_student_status`.

## Risk Assessment

- **Duplicate re-import**: staff re-uploading the same file after a partial
  failure re-attempts already-placed students; `InitializeStudentPlacementAction`
  already throws `InvalidProgressionState` for those, so they show as
  per-row errors, not silent double-placement. No extra dedup logic needed.
- **Long-running request**: 405 rows × one `DB::transaction` each (inside
  `InitializeStudentPlacementAction`) is the same per-row transaction cost
  the existing student-actions importer already accepts at up to 1000 rows;
  no batching change needed for this file size.
