---
title: Excel Export Operations
status: current
type: runbook
scope: Shared Excel export implementation and operations
last_verified: "2026-07-25"
owner: Platform Team
audience:
  - developers
  - support engineers
---

# Excel Export Operations

## Shared export boundary

`app/Services/ExcelExportService.php` is the shared wrapper around
Maatwebsite Excel for download, storage, raw output, timestamped filenames, and
MIME lookup. New export flows should first inspect existing domain export
actions and classes; the shared wrapper is infrastructure, not a place for
domain queries.

The service sanitizes download filenames and storage paths to letters, digits,
underscores, and hyphens. Callers that need nested storage paths must verify the
result because sanitization removes extensions and path separators.

## Writer type caveat

The service accepts a `writerType` argument. `raw()` passes it directly to
Maatwebsite Excel, but the current `download()` and `store()` implementations
use it only for the filename/path and do not pass an explicit writer constant
to the facade. Do not assume changing the extension alone guarantees a matching
writer. Cover any non-XLSX use with a focused response/content test.

## Memory behavior

Export memory usage is primarily determined by the export concern:

- a `FromCollection` export materializes its collection;
- a `FromQuery` export allows the package to process a query in chunks;
- per-row relationship or service lookups can still create time and query
  pressure even when peak memory is acceptable.

The grade-template path in
`app/Services/AssessmentGradeExcelService.php` temporarily changes PHP's
`memory_limit` using `config/excel-memory.php` and restores it afterward. Its
`App\Exports\OptimizedGradeTemplateExport` still uses a collection path, so the
memory override is a guardrail, not streaming.

Do not claim an export is chunked from a helper method alone. Verify the export
class concern and its query/collection implementation.

## Adding or changing an export

1. Reuse the domain's existing export Action or Query.
2. Keep authorization and filters in the request/controller boundary.
3. Use a Maatwebsite concern appropriate to the expected row count.
4. Eager-load required relationships and avoid per-row service calls.
5. Generate a sanitized, deterministic filename with an appropriate extension.
6. Add a focused feature test for headers, filename, row visibility, and the
   important filter or authorization boundary.
7. Exercise a representative large dataset when the change is intended to
   improve memory behavior.

Existing export entry points can be found with:

```bash
rg -n "Excel::(download|store|raw)|ExcelExportService|FromCollection|FromQuery" \
  app tests
```

## Diagnosis

### File extension and content disagree

Inspect the facade call and writer selection. Passing `csv` to
`ExcelExportService::download()` currently changes the filename but does not
explicitly select the CSV writer.

### Export exhausts memory

Inspect whether the export implements `FromCollection`, calls `get()`, or
enriches every row individually. A larger `memory_limit` may buy time but does
not remove a full-collection design.

### Stored export cannot be found

Inspect the sanitized path, configured disk, and the disk root in
`config/filesystems.php`. Remember that the shared service strips path
separators during sanitization.

### Export contains unauthorized rows

Treat this as an authorization/query defect. The export service does not add
campus or role scoping; the owning Action or Query must do so.

## Verification

Run the feature test that owns the changed export. Examples of focused export
coverage live under `tests/Feature/Academic/`, `tests/Feature/Finance/`, and
`tests/Feature/StudentApplication/`.
