<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Exports\StudentApplicationExport;
use App\Models\StudentApplication;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

final class ExportApplicationsAction
{
    /** @param array{format: 'xlsx'|'csv', scope: 'filtered'|'all', search?: mixed, status?: mixed, campus_code?: mixed, intake?: mixed, sort?: string, direction?: string, current_campus_code?: ?string} $data */
    public static function run(array $data): mixed
    {
        $filters = ['search' => $data['search'] ?? null, 'status' => $data['status'] ?? null, 'campus_code' => $data['campus_code'] ?? null, 'intake' => $data['intake'] ?? null];
        $query = StudentApplication::query();

        if ($data['current_campus_code'] !== null) {
            $query->where('campus_code', $data['current_campus_code']);
        }
        if ($data['scope'] === 'filtered') {
            if ($filters['search']) {
                $query->where(fn ($query) => $query->where('full_name', 'like', '%'.$filters['search'].'%')->orWhere('email', 'like', '%'.$filters['search'].'%')->orWhere('national_id', 'like', '%'.$filters['search'].'%')->orWhere('phone', 'like', '%'.$filters['search'].'%'));
            }
            foreach (['status', 'campus_code', 'intake'] as $filter) {
                if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '' && $filters[$filter] !== 'all') {
                    $query->where($filter, $filters[$filter]);
                }
            }
        }

        $query->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc');
        $filename = 'student_applications_'.now()->format('Y-m-d_H-i-s');
        $export = new StudentApplicationExport($query, $filters);

        return $data['format'] === 'csv'
            ? ExcelFacade::download($export, $filename.'.csv', Excel::CSV)
            : ExcelFacade::download($export, $filename.'.xlsx');
    }
}
