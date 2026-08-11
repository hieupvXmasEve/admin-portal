<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DeferReturnWatchlistExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected Builder $query
    ) {}

    public function query(): Builder
    {
        return $this->query->orderBy('rows.action_id');
    }

    public function headings(): array
    {
        return [
            'Bucket',
            'Severity',
            'Student Code',
            'Student Name',
            'Campus',
            'Action Type',
            'Anchor Semester',
            'Anchor Start Date',
            'Days Elapsed',
            'Student Status',
        ];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        return [
            $row->bucket,
            $row->severity ?? '',
            $row->student_code,
            mb_strtoupper((string) $row->student_name, 'UTF-8'),
            $row->campus_name ?? '',
            $row->action_type,
            $row->anchor_semester_code ?? '',
            $row->anchor_start_date ?? '',
            $row->days_elapsed ?? '',
            $row->student_status,
        ];
    }
}
