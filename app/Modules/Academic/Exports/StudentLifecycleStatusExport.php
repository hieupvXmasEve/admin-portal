<?php

declare(strict_types=1);

namespace App\Modules\Academic\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentLifecycleStatusExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Collection $rows
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'Full Name',
            'Program',
            'Intake Semester',
            'Intake Year',
            'Status at Selected Semester',
            'Current Status',
            'Latest Action Type',
            'Latest Action Semester',
            'Defer Start Semester',
            'Dropout Semester',
            'Campus',
            'NE',
            'Updated At',
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function map($row): array
    {
        return [
            $row['student_id'] ?? '',
            $row['full_name'] ?? '',
            $row['program_name'] ?? '',
            $row['intake_semester'] ?? '',
            $row['intake_year'] ?? '',
            $row['status_at_selected_semester'] ?? '',
            $row['current_status'] ?? '',
            $row['latest_action_type'] ?? '',
            $row['latest_action_effective_semester'] ?? '',
            $row['defer_start_semester'] ?? '',
            $row['dropout_semester'] ?? '',
            $row['current_campus'] ?? '',
            ! empty($row['ne']) ? 'Yes' : 'No',
            $row['updated_at'] ?? '',
        ];
    }
}

