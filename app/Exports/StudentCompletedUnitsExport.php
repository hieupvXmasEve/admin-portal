<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentCompletedUnitsExport implements FromArray, WithStyles, WithTitle
{
    /** Columns that are always present, in order, before any semester column. */
    private const FIXED_COLUMNS = ['Mã SV', 'Họ tên', 'Ngành', 'Trạng thái', 'Môn GC'];

    /** Columns appended after the Major column(s). */
    private const TRAILING_COLUMNS = ['Số môn', 'TC đạt', 'TC toàn CT'];

    private int $filterRowsCount = 0;

    private int $headerRow = 0;

    private int $dataStartRow = 0;

    private int $dataEndRow = 0;

    private int $columnCount = 0;

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  bool  $splitBySemester  When no semester filter is active, the merged
     *                                 "Môn Major" column is replaced by one column
     *                                 per semester present in the data.
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $filters,
        private readonly bool $splitBySemester = false,
    ) {}

    public function array(): array
    {
        $out = [];

        $out[] = ['STUDENT REGISTERED UNITS'];
        $out[] = [''];

        foreach ($this->filterLabels() as $label => $value) {
            $out[] = ["{$label}:", $value];
        }
        $out[] = ['Generated at:', now()->format('Y-m-d H:i:s')];

        $semesterColumns = $this->splitBySemester ? $this->semesterColumns() : [];

        if ($semesterColumns !== []) {
            // A unit retaken across semesters is listed under each semester it
            // was registered in, so these columns can total more than "Số môn".
            $out[] = ['Ghi chú:', 'Môn học lại xuất hiện ở mọi kì đã đăng ký; "Số môn" đếm mỗi môn một lần.'];
        }

        $out[] = [''];

        $this->filterRowsCount = count($out);
        $this->headerRow = $this->filterRowsCount + 1;

        $majorHeaders = $semesterColumns === []
            ? ['Môn Major']
            : array_map(static fn (array $semester): string => "Major {$semester['code']}", $semesterColumns);

        $out[] = [...self::FIXED_COLUMNS, ...$majorHeaders, ...self::TRAILING_COLUMNS];
        $this->columnCount = count(self::FIXED_COLUMNS) + count($majorHeaders) + count(self::TRAILING_COLUMNS);
        $this->dataStartRow = $this->headerRow + 1;

        foreach ($this->rows as $row) {
            $majorCells = $semesterColumns === []
                ? [$this->codeList($row['major'])]
                : array_map(
                    fn (array $semester): string => $this->codeList($row['units_by_semester'][$semester['id']]['units'] ?? []),
                    $semesterColumns,
                );

            $out[] = [
                $row['student_id'],
                $row['full_name'],
                $row['program'] ?? '',
                $row['status'] ?? '',
                $this->codeList($row['gc']),
                ...$majorCells,
                $row['units_count'],
                $row['credits_earned'],
                $row['credits_required'],
            ];
        }

        $this->dataEndRow = count($out);

        return $out;
    }

    /**
     * Semesters that actually carry a registration, oldest first — so an
     * unfiltered export doesn't sprout a column for every semester on record.
     *
     * @return list<array{id: int, code: string}>
     */
    private function semesterColumns(): array
    {
        $semesters = [];

        foreach ($this->rows as $row) {
            foreach ($row['units_by_semester'] ?? [] as $semesterId => $semester) {
                $semesters[(int) $semesterId] = $semester;
            }
        }

        uasort($semesters, static fn (array $a, array $b): int => strcmp((string) $a['sort'], (string) $b['sort']));

        return array_map(
            static fn (int $id): array => ['id' => $id, 'code' => (string) $semesters[$id]['code']],
            array_keys($semesters),
        );
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

        if (! empty($this->filters['semester_name'])) {
            $labels['Semester'] = (string) $this->filters['semester_name'];
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
        // Column count varies with the semester split, so it can run past 'Z'.
        $lastColumn = Coordinate::stringFromColumnIndex(max(1, $this->columnCount));

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

        for ($index = 1; $index <= max(1, $this->columnCount); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        return [];
    }

    public function title(): string
    {
        return 'Registered Units';
    }
}
