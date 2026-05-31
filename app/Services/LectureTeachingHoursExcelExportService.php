<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Lecture\GetTeachingHoursAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LectureTeachingHoursExcelExportService
{
    public function __construct(
        private readonly GetTeachingHoursAction $teachingHoursAction
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportTeachingHoursToExcel(array $filters, int $campusId): string
    {
        $rows = $this->teachingHoursAction->getForExport($filters, $campusId);
        $fileName = 'teaching_hours_export_'.now()->format('Y-m-d_H-i-s').'.xlsx';

        Excel::store(new TeachingHoursSummarySheet($rows), 'temp/'.$fileName, 'local');

        return Storage::disk('local')->path('temp/'.$fileName);
    }
}

class TeachingHoursSummarySheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly Collection $rows
    ) {}

    public function collection(): Collection
    {
        return $this->rows
            ->values()
            ->map(fn (array $row, int $index): array => [
                'no' => $index + 1,
                'lecturer_name' => $row['lecture_name'],
                'email_account' => $row['email_account'],
                'employee_id' => $row['employee_id'],
                'type' => $row['employment_type_label'],
                'course_day' => $row['course_list'],
                'mon_giang_day' => $row['teaching_subjects'],
                'total_hours' => number_format((float) $row['total_hours'], 2, '.', ''),
            ]);
    }

    public function headings(): array
    {
        return [
            'No',
            'Lecturer name',
            'Email account',
            'Employee ID',
            'Type',
            'Course dạy',
            'Môn giảng dạy của GV',
            'Total hours',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 28,
            'C' => 22,
            'D' => 18,
            'E' => 16,
            'F' => 36,
            'G' => 28,
            'H' => 14,
        ];
    }

    public function title(): string
    {
        return 'Teaching Hours';
    }
}
