# Excel Grade Export Memory Notes (Current)

Last updated: 2026-03-02  
Owner: Platform Team  
Status: Partial optimization snapshot

## What Exists in Code

- `app/Services/AssessmentGradeExcelService.php`
    - temporary `memory_limit` override via `config('excel-memory.memory_limit', '512M')`
    - optional memory logging via `config('excel-memory.log_memory_usage', false)`
    - export path uses `App\Exports\OptimizedGradeTemplateExport`
- `app/Exports/OptimizedGradeTemplateExport.php`
    - simplified headings/column widths
    - still loads full student query result with `$query->get()`
    - score enrichment uses per-student calls (`getStudentScore`) after collection load

## Accuracy Fixes vs Older Doc

- removed unverified performance claims (for example "5000+ students without issues")
- removed claim that export is fully generator-streaming; current export class implements `FromCollection`
- retained only code-verified configuration and flow details

## Practical Guidance

- current changes reduce some memory pressure but do not eliminate full-collection risks
- for very large classes, rework toward `FromQuery`/chunk-oriented export patterns if needed
