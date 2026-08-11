<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Exports\StudentApplicationExport;
use App\Models\StudentApplication;
use App\Modules\Admissions\Queries\ListApplicationsQuery;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

final class ExportApplicationsAction
{
    /** @param array{format: 'xlsx'|'csv', search?: mixed, status?: mixed, intake?: mixed, sort?: string, direction?: string, current_campus_code?: ?string} $data */
    public static function run(array $data): mixed
    {
        // D11: `scope` is gone. The UI only ever sent `scope=filtered`, and
        // `scope=all` was a URL-edit bypass of every filter (still campus-scoped,
        // but a full-campus PII dump). Filters always apply now — one code path.
        $filters = array_merge(
            ['search' => $data['search'] ?? null, 'status' => $data['status'] ?? null, 'intake' => $data['intake'] ?? null],
            array_intersect_key($data, array_flip(ListApplicationsQuery::ADVANCED_FILTER_KEYS)),
        );
        $query = StudentApplication::query();

        // Fail closed: a null campus means the session's campus binding could
        // not be resolved, not "export every campus" (see ListApplicationsQuery).
        if ($data['current_campus_code'] === null) {
            $query->whereNull('id');
        } else {
            $query->where('campus_code', $data['current_campus_code']);
        }

        // Same method the list query uses, so export can never drift from what
        // is on screen (adds student_code to the search predicate for parity).
        app(ListApplicationsQuery::class)->scopeFilters($query, $filters);

        $query->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc');
        $filename = 'student_applications_'.now()->format('Y-m-d_H-i-s');
        $export = new StudentApplicationExport($query, $filters);

        return $data['format'] === 'csv'
            ? ExcelFacade::download($export, $filename.'.csv', Excel::CSV)
            : ExcelFacade::download($export, $filename.'.xlsx');
    }
}
