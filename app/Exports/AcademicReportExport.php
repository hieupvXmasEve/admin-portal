<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AcademicReportExport implements FromArray, WithStyles, WithTitle
{
    protected array $data;
    protected Collection $units;

    public function __construct(array $report)
    {
        $this->data = $report['data'] instanceof Collection ? $report['data']->toArray() : $report['data'];
        $this->units = $report['units'];
    }

    public function array(): array
    {
        $rows = [];

        // Row 1: Main headers (Unit Names)
        $header1 = ['Full Name', 'Student ID'];
        foreach ($this->units as $unit) {
            $header1[] = $unit->name;
            $header1[] = ''; // For "Score" column merging
        }
        $header1[] = 'Sum of % attendance';
        $header1[] = 'Sum of GPA';
        $rows[] = $header1;

        // Row 2: Sub headers (attendance / total score)
        $header2 = ['', ''];
        foreach ($this->units as $unit) {
            $header2[] = 'attendance';
            $header2[] = 'total score';
        }
        $header2[] = '';
        $header2[] = '';
        $rows[] = $header2;

        // Data rows
        foreach ($this->data as $item) {
            $row = [
                $item['full_name'],
                $item['student_id'],
            ];

            foreach ($this->units as $unit) {
                $result = $item['course_results'][$unit->id] ?? null;
                $row[] = $result && $result['attendance'] !== null ? $result['attendance'] . '%' : '';
                $row[] = $result && $result['score'] !== null ? $result['score'] . '%' : '';
            }

            $row[] = $item['avg_attendance'] !== null ? $item['avg_attendance'] . '%' : '';
            $row[] = $item['cumulative_gpa'] ?? '';

            $rows[] = $row;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $this->units->count() * 2 + 2);
        
        // Merge "Full Name" and "Student ID" vertically
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');

        // Merge Course Codes horizontally
        $colIndex = 3;
        foreach ($this->units as $unit) {
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->mergeCells("{$startCol}1:{$endCol}1");
            $colIndex += 2;
        }

        // Merge Sum columns vertically
        $attendanceSumCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $gpaSumCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
        $sheet->mergeCells("{$attendanceSumCol}1:{$attendanceSumCol}2");
        $sheet->mergeCells("{$gpaSumCol}1:{$gpaSumCol}2");

        // Style the headers
        $sheet->getStyle("A1:{$gpaSumCol}2")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4A5568'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);

        // Auto-size columns
        for ($i = 1; $i <= $colIndex + 1; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Style all data cells
        $lastRow = count($this->data) + 2;
        $sheet->getStyle("A3:{$gpaSumCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Left align names
        $sheet->getStyle("A3:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        return [];
    }

    public function title(): string
    {
        return 'Academic Report';
    }
}
