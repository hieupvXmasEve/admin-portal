# Excel Export Service (Current API)

Last updated: 2026-03-02  
Owner: Platform Team  
Status: Code-verified summary

Service file: `app/Services/ExcelExportService.php`

## Public Methods

- `download(object $exportClass, string $filename, string $writerType = 'xlsx'): BinaryFileResponse`
- `store(object $exportClass, string $path, string $disk = 'local', string $writerType = 'xlsx'): bool`
- `raw(object $exportClass, string $writerType = 'xlsx'): string`
- `generateFilenameWithTimestamp(string $prefix, string $extension = 'xlsx'): string`
- `getMimeType(string $writerType): string`

## Implementation Notes

- filename/path sanitization is enforced via `sanitizeFilename(...)`
- timestamp format from implementation: `Y-m-d_His`
- MIME map includes `xlsx`, `xls`, `csv`, `ods`, `html`, `pdf`

## Known Drift to Keep in Mind

- method signatures accept `$writerType`, but `download()` and `store()` currently call `Excel::download(...)` and `Excel::store(...)` without explicitly passing writer type.
- older docs with broad frontend/controller examples were removed because they were template-style and not tied to current project routes.

## Related Files

- `app/Services/AssessmentGradeExcelService.php`
- `app/Exports/OptimizedGradeTemplateExport.php`
