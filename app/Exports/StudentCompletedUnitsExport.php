<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentCompletedUnitsExport implements FromArray, WithStyles, WithTitle
{
    private const COLUMNS = ['Mã SV', 'Họ tên', 'Ngành', 'Môn GC', 'Môn Major', 'Số môn', 'TC đạt', 'TC toàn CT'];

    private int $filterRowsCount = 0;

    private int $headerRow = 0;

    private int $dataStartRow = 0;

    private int $dataEndRow = 0;

    /** @param  Collection<int, array<string, mixed>>  $rows */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $filters,
    ) {}

    public function array(): array
    {
        $out = [];

        $out[] = ['STUDENT COMPLETED UNITS'];
        $out[] = [''];

        foreach ($this->filterLabels() as $label => $value) {
            $out[] = ["{$label}:", $value];
        }
        $out[] = ['Generated at:', now()->format('Y-m-d H:i:s')];
        $out[] = [''];

        $this->filterRowsCount = count($out);
        $this->headerRow = $this->filterRowsCount + 1;

        $out[] = self::COLUMNS;
        $this->dataStartRow = $this->headerRow + 1;

        foreach ($this->rows as $row) {
            $out[] = [
                $row['student_id'],
                $row['full_name'],
                $row['program'] ?? '',
                $this->codeList($row['gc']),
                $this->codeList($row['major']),
                $row['units_count'],
                $row['credits_earned'],
                $row['credits_required'],
            ];
        }

        $this->dataEndRow = count($out);

        return $out;
    }

    /** @return array<string, string> */
    private function filterLabels(): array
    {
        $labels = [];

        if (! empty($this->filters['campus_name'])) {
            $labels['Campus'] = (string) $this->filters['campus_name'];
        }

        if (! empty($this->filters['program_name'])) {
            $labels['Program'] = (string) $this->filters['program_name'];
        }

        if (! empty($this->filters['keyword'])) {
            $labels['Keyword'] = (string) $this->filters['keyword'];
        }

        return $labels;
    }

    /** @param  array<int, array{code: string, name: string, credits: string}>  $units */
    private function codeList(array $units): string
    {
        return implode(', ', array_map(static fn (array $unit): string => $unit['code'], $units));
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = 'H';

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
        ]);

        for ($i = 3; $i <= $this->filterRowsCount; $i++) {
            $sheet->getStyle("A{$i}")->getFont()->setBold(true);
        }

        $sheet->getStyle("A{$this->headerRow}:{$lastColumn}{$this->headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4A5568'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);

        if ($this->dataEndRow >= $this->dataStartRow) {
            $sheet->getStyle("A{$this->dataStartRow}:{$lastColumn}{$this->dataEndRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);
        }

        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }

    public function title(): string
    {
        return 'Completed Units';
    }
}
