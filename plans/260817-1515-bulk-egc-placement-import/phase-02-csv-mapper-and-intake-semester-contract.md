---
phase: 2
title: "CSV mapper"
status: done
priority: P1
effort: "3h"
dependencies: [1]
---

# Phase 2: CSV mapper

## Overview

Row-level parsing/validation: turn one CSV row into either a normalized
placement payload or a list of skip/error reasons, without touching the
database beyond read-only lookups (`Student`, level map). No writes happen in
this phase — mirrors `StudentActionExcelRowMapper`'s pure-mapping shape.

## Requirements

- Functional:
  - Header detection is by column *name* (`Student ID`, `Level`), not fixed
    position — the source file has 13 columns, only 2 are used, and staff
    exports vary column order/count over time.
  - Level map: `FOUNDATION`→0, `EGC1`→1 .. `EGC5`→5 (uppercase, trim before
    compare). Anything else (`EGC6`, `GCS*`, `x`, blank, unrecognized) is a
    skip with a specific machine-readable reason code.
  - Student lookup: `Student::query()->where('student_id', trim($raw))->first()`.
- Non-functional: pure/stateless mapper class, easy to unit test without HTTP
  or file I/O (accepts already-parsed array rows, like
  `StudentActionExcelRowMapper::mapRow`).

## Related Code Files

- Create: `app/Modules/Academic/Progression/Support/BulkEgcPlacementRowMapper.php`
- Read (reference, no changes): `app/Modules/Academic/Progression/Support/StudentActionExcelRowMapper.php`

## Implementation Steps

1. `validateHeaders(array $headerRow): array` — trim each header cell,
   case-insensitive match for `student id` and `level` columns, return
   `['ok' => true, 'student_id_col' => int, 'level_col' => int]` or
   `['ok' => false, 'missing' => [...]]` when either column is absent.

2. `mapRow(array $row, int $rowNumber, int $studentIdCol, int $levelCol): array`
   returns a shape parallel to `StudentActionExcelRowMapper::mapRow()`:
   ```php
   [
       'row_number' => $rowNumber,
       'status' => 'valid'|'skip'|'error',
       'skip_reason' => ?string,      // one of the codes below when status=skip
       'student' => ['id' => ?int, 'student_id' => ?string, 'full_name' => ?string],
       'level_raw' => ?string,
       'level' => ?int,               // 0-5 when status=valid
       'normalized_payload' => ?array, // ['student_id' => int, 'english_level' => int] when valid
   ]
   ```

3. Skip reason codes (exact strings, used by the preview table and by the
   summary counters in Phase 3):
   - `student_id_missing` — `Student ID` cell blank.
   - `student_not_found` — no `Student` row for that `student_id`.
   - `level_missing` — `Level` cell blank.
   - `level_excluded_gcs` — `Level` starts with `GCS` (case-insensitive).
   - `level_unmapped` — anything else not in the Foundation/EGC1-5 map
     (covers `EGC6`, `x`, typos).

4. Do **not** check "already placed" here — that is a write-path concern
   (`InitializeStudentPlacementAction` already guards it via
   `InvalidProgressionState`); Phase 3's action catches that per-row instead
   of duplicating the check.

## Success Criteria

- [x] Given the real `student_egc.csv` header row, `validateHeaders` finds
      both columns regardless of the other 11 column names/order.
- [x] Mapping the real file's ~405 data rows classifies ~219 `GCS5` as
      `level_excluded_gcs`, ~34 `EGC6` as `level_unmapped`, ~2 `x` as
      `level_unmapped`, ~7 blank as `level_missing`, and the remainder
      (Foundation/EGC1-5) as `valid` (pending student lookup).
- [x] Unknown `Student ID` values map to `student_not_found`, not an
      exception.

## Risk Assessment

Level-string variants (extra spaces, different casing, e.g. `egc 1` vs
`EGC1`) could silently fall into `level_unmapped` instead of mapping
correctly. Mitigate by stripping internal whitespace before uppercasing in
the map lookup, and surfacing `level_unmapped` rows with their raw string in
the preview table so staff can spot-check before executing.
