<?php

declare(strict_types=1);

namespace App\Modules\Academic\Exports;

use App\Models\StudentActionLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentActionLogsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected Builder $query
    ) {}

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'Student Name',
            'Student Email',
            'Action Type',
            'Reason',
            'Changed At',
            'Changed By',
            'Signed At',
            'Missing Documents',
            'From Semester',
            'EGC From Block',
            'Return Semester',
            'Intended Intake Semester',
            'Dropout Semester',
            'Effective Semester',
            'From Campus',
            'To Campus',
            'Effective At',
            'Previous Status',
            'New Status',
            'Notes',
        ];
    }

    /**
     * @param  StudentActionLog  $row
     */
    public function map($row): array
    {
        return [
            $row->student?->student_id ?? '',
            $row->student?->full_name ?? '',
            $row->student?->email ?? '',
            $row->action_type?->labelEn() ?? $row->action_type,
            $row->reason,
            $row->created_at?->format('Y-m-d H:i:s') ?? '',
            $row->changedBy?->name ?? '',
            $row->signed_at?->format('Y-m-d') ?? '',
            $row->missing_documents ? 'Yes' : 'No',
            $row->fromSemester?->name ?? '',
            $row->egc_defer_from_block_number
                ? 'Block '.$row->egc_defer_from_block_number
                : '',
            $row->returnSemester?->name ?? '',
            $row->intendedIntakeSemester?->name ?? '',
            $row->dropoutSemester?->name ?? '',
            $row->effectiveSemester?->name ?? '',
            $row->fromCampus?->name ?? '',
            $row->toCampus?->name ?? '',
            $row->effective_at?->format('Y-m-d H:i:s') ?? '',
            $row->previous_status ?? '',
            $row->new_status ?? '',
            $row->notes ?? '',
        ];
    }
}
