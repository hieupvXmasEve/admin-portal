<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LecturerGpaReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $semester
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly ?array $semester,
    ) {}

    public function array(): array
    {
        return [
            ['Lecturer GPA Report'],
            ['Semester', $this->semester['name'] ?? 'N/A'],
            ['Generated At', now()->format('Y-m-d H:i:s')],
            [],
            ['No', 'Lecturer name', 'Email account', 'Employee ID', 'Type', 'Courses taught', 'GPA', 'Evaluated classes', 'Responses'],
            ...$this->dataRows(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:I1')->applyFromArray($this->headerStyle('1F2937', 'E5E7EB'));
        $sheet->getStyle('A5:I5')->applyFromArray($this->headerStyle('FFFFFF', '4A5568'));
        $sheet->getStyle('A:I')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('F:F')->getAlignment()->setWrapText(true);

        return [];
    }

    public function title(): string
    {
        return 'Lecturer GPA';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function dataRows(): array
    {
        return $this->rows
            ->values()
            ->map(fn (array $row, int $index): array => [
                $index + 1,
                $row['lecturer_name'] ?? '',
                $row['email_account'] ?? '',
                $row['employee_id'] ?? '',
                $row['type_label'] ?? '',
                $row['courses_display'] ?? '',
                $row['gpa'] ?? 'N/A',
                $row['evaluated_classes_count'] ?? 0,
                $row['responses_count'] ?? 0,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function headerStyle(string $fontColor, string $fillColor): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => $fontColor],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $fillColor],
            ],
        ];
    }
}
