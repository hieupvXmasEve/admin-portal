<?php

declare(strict_types=1);

namespace App\Modules\Academic\Exports;

use App\Models\GpaCalculation;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GpaHistoryExport implements FromQuery, WithHeadings, WithMapping
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
            'Student Number',
            'Full Name',
            'Program',
            'Semester',
            'Semester GPA',
            'Cumulative GPA',
            'Academic Standing',
            'Credits Earned',
            'Status',
        ];
    }

    /**
     * @param  GpaCalculation  $row
     */
    public function map($row): array
    {
        return [
            $row->student->student_id ?? '',
            $row->student->full_name ?? '',
            $row->program->name ?? '',
            $row->semester->name ?? '',
            number_format((float) $row->semester_gpa, 2),
            number_format((float) $row->cumulative_gpa, 2),
            ucfirst($row->academic_standing ?? ''),
            number_format((float) $row->cumulative_credit_points_earned, 1),
            $row->is_finalized ? 'Finalized' : 'Draft',
        ];
    }
}
