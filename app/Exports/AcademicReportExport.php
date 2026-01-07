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
    protected array $stats;
    protected array $filters;

    protected int $filterRowsCount = 0;
    protected int $dataHeaderRow = 0;
    protected int $dataStartRow = 0;
    protected int $dataEndRow = 0;
    protected int $statsStartRow = 0;

    public function __construct(array $report)
    {
        $this->data = $report['data'] instanceof Collection ? $report['data']->toArray() : $report['data'];
        $this->units = $report['units'];
        $this->stats = $report['stats'] ?? [];
        $this->filters = $report['filters'] ?? [];
    }

    public function array(): array
    {
        $rows = [];

        // === Filter Information Section ===
        $rows[] = ['ACADEMIC REPORT'];
        $rows[] = [''];

        $filterLabels = [
            'semester' => 'Semester',
            'program' => 'Program',
            'status' => 'Status',
            'keyword' => 'Search Keyword',
        ];

        foreach ($filterLabels as $key => $label) {
            if (isset($this->filters[$key]) && $this->filters[$key] !== null) {
                $rows[] = [$label . ':', $this->filters[$key]];
            }
        }

        $rows[] = ['Generated at:', now()->format('Y-m-d H:i:s')];
        $rows[] = [''];

        $this->filterRowsCount = count($rows);
        $this->dataHeaderRow = $this->filterRowsCount + 1;
        $this->dataStartRow = $this->dataHeaderRow + 2;

        // === Data Section ===
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
                $attendance = $result && $result['attendance'] !== null ? $result['attendance'] . '%' : '';
                $score = $result && $result['score'] !== null ? $result['score'] : '';
                $grade = $result && $result['grade'] !== null ? ' (' . $result['grade'] . ')' : '';
                $row[] = $attendance;
                $row[] = $score . $grade;
            }

            $row[] = $item['avg_attendance'] !== null ? $item['avg_attendance'] . '%' : '';
            $row[] = $item['cumulative_gpa'] ?? '';

            $rows[] = $row;
        }

        $this->dataEndRow = count($rows);

        // === Grade Statistics Section ===
        $rows[] = [''];
        $rows[] = [''];
        $this->statsStartRow = count($rows) + 1;

        $rows[] = ['GRADE STATISTICS'];
        $rows[] = [''];

        if (!empty($this->stats['grade_distribution'])) {
            $totalGrades = $this->stats['total_grades'] ?? array_sum($this->stats['grade_distribution']);

            // Header row for stats
            $rows[] = ['Grade', 'Count', 'Percentage'];

            foreach ($this->stats['grade_distribution'] as $grade => $count) {
                $percentage = $totalGrades > 0 ? round(($count / $totalGrades) * 100, 1) . '%' : '0%';
                $rows[] = [$grade, $count, $percentage];
            }

            $rows[] = [''];
            $rows[] = ['Total Records', $totalGrades, '100%'];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(2 + $this->units->count() * 2 + 2);

        // === Style Filter Section ===
        // Title row
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
        ]);

        // Filter labels (bold)
        for ($i = 3; $i <= $this->filterRowsCount; $i++) {
            $sheet->getStyle("A{$i}")->getFont()->setBold(true);
        }

        // === Style Data Section Headers ===
        // Merge "Full Name" and "Student ID" vertically
        $sheet->mergeCells("A{$this->dataHeaderRow}:A" . ($this->dataHeaderRow + 1));
        $sheet->mergeCells("B{$this->dataHeaderRow}:B" . ($this->dataHeaderRow + 1));

        // Merge Course Codes horizontally
        $colIndex = 3;
        foreach ($this->units as $unit) {
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->mergeCells("{$startCol}{$this->dataHeaderRow}:{$endCol}{$this->dataHeaderRow}");
            $colIndex += 2;
        }

        // Merge Sum columns vertically
        $attendanceSumCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $gpaSumCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
        $sheet->mergeCells("{$attendanceSumCol}{$this->dataHeaderRow}:{$attendanceSumCol}" . ($this->dataHeaderRow + 1));
        $sheet->mergeCells("{$gpaSumCol}{$this->dataHeaderRow}:{$gpaSumCol}" . ($this->dataHeaderRow + 1));

        // Style the data headers
        $headerEndRow = $this->dataHeaderRow + 1;
        $sheet->getStyle("A{$this->dataHeaderRow}:{$gpaSumCol}{$headerEndRow}")->applyFromArray([
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
        $sheet->getStyle("A{$this->dataStartRow}:{$gpaSumCol}{$this->dataEndRow}")->applyFromArray([
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
        $sheet->getStyle("A{$this->dataStartRow}:A{$this->dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // === Style Statistics Section ===
        // Stats title
        $sheet->getStyle("A{$this->statsStartRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
        ]);

        // Stats header row
        $statsHeaderRow = $this->statsStartRow + 2;
        $sheet->getStyle("A{$statsHeaderRow}:C{$statsHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4A5568'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);

        // Stats data rows
        $gradeCount = count($this->stats['grade_distribution'] ?? []);
        $statsDataStartRow = $statsHeaderRow + 1;
        $statsDataEndRow = $statsDataStartRow + $gradeCount + 1; // +1 for total row

        $sheet->getStyle("A{$statsDataStartRow}:C{$statsDataEndRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Total row styling
        $totalRow = $statsDataEndRow;
        $sheet->getStyle("A{$totalRow}:C{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
        ]);

        return [];
    }

    public function title(): string
    {
        return 'Academic Report';
    }
}
