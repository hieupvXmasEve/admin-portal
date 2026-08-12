<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ScholarshipRestorationWatchlistExport implements FromCollection, WithHeadings, WithMapping
{
    /** @param  Collection<int, array<string, mixed>>  $rows  Already-decorated, already-filtered watchlist rows. */
    public function __construct(protected Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Student Code',
            'Student Name',
            'Target Semester',
            'Original',
            'Adjusted',
            'Effective',
            'Proposal State',
            'Verdict',
            'Evaluated Semester',
            'Semester GPA',
            'Cumulative GPA',
            'Min Attendance %',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['student']['student_code'],
            $row['student']['full_name'],
            $row['target_semester']['code'] ?? $row['target_semester']['name'] ?? '',
            $row['original_amount'],
            $row['adjusted_amount'],
            $row['effective_amount'],
            $row['proposal_state'],
            $row['verdict'],
            $row['evaluated_semester']['code'] ?? $row['evaluated_semester']['name'] ?? '',
            $row['semester_gpa'] ?? '',
            $row['cumulative_gpa'] ?? '',
            $row['attendance_min_percentage'] ?? '',
        ];
    }
}
