<?php

declare(strict_types=1);

namespace App\Modules\Academic\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentActionImportTemplateExport implements FromArray, WithEvents, WithHeadings, WithStyles
{
    private const MAX_ROWS = 1000;

    public function __construct(
        private readonly array $actionTypes,
        private readonly array $semesterCodes,
        private readonly array $campusCodes
    ) {}

    public function array(): array
    {
        return [
            ['SV001', 'ACADEMIC_DEFER', 'Bảo lưu theo quyết định', '2025FA', '2026SP', 'yes', '', '', '', '', '2026-02-25', 'QD-2026-001', '2026-02-20', 'Phong Dao Tao', 'Ghi chú nội bộ'],
            ['SV002', 'ACADEMIC_RESUME', 'Đi học lại', '', '2026SP', '', '', '', '', '', '', 'QD-2026-002', '2026-02-21', 'Phong Dao Tao', ''],
        ];
    }

    public function headings(): array
    {
        return [
            'student_code',
            'action_type',
            'reason',
            'from_semester_code',
            'return_semester_code',
            'defer_preserve_tuition',
            'dropout_semester_code',
            'from_campus_code',
            'to_campus_code',
            'effective_at',
            'signed_at',
            'decision_number',
            'decision_signed_at',
            'decision_signer',
            'notes',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE2E2E2'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $this->writeHelperLists($sheet);
                $this->applySelectValidation($sheet);
            },
        ];
    }

    private function writeHelperLists(Worksheet $sheet): void
    {
        $this->writeList($sheet, 'R', array_values($this->actionTypes));
        $this->writeList($sheet, 'S', array_values($this->semesterCodes));
        $this->writeList($sheet, 'T', array_values($this->campusCodes));
        $this->writeList($sheet, 'U', ['yes', 'no']);

        foreach (['R', 'S', 'T', 'U'] as $col) {
            $sheet->getColumnDimension($col)->setVisible(false);
        }
    }

    private function applySelectValidation(Worksheet $sheet): void
    {
        $this->applyRangeValidation($sheet, 'B2:B' . self::MAX_ROWS, '$R$2:$R$' . max(2, count($this->actionTypes) + 1));
        $semesterFormula = '$S$2:$S$' . max(2, count($this->semesterCodes) + 1);
        $this->applyRangeValidation($sheet, 'D2:D' . self::MAX_ROWS, $semesterFormula);
        $this->applyRangeValidation($sheet, 'E2:E' . self::MAX_ROWS, $semesterFormula);
        $this->applyRangeValidation($sheet, 'G2:G' . self::MAX_ROWS, $semesterFormula);
        $this->applyRangeValidation($sheet, 'H2:H' . self::MAX_ROWS, '$T$2:$T$' . max(2, count($this->campusCodes) + 1));
        $this->applyRangeValidation($sheet, 'I2:I' . self::MAX_ROWS, '$T$2:$T$' . max(2, count($this->campusCodes) + 1));
        $this->applyRangeValidation($sheet, 'F2:F' . self::MAX_ROWS, '$U$2:$U$3');
    }

    private function applyRangeValidation(Worksheet $sheet, string $targetRange, string $listRange): void
    {
        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST);
        $dv->setAllowBlank(true);
        $dv->setShowDropDown(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorStyle(DataValidation::STYLE_STOP);
        $dv->setErrorTitle('Invalid value');
        $dv->setError('Please choose from dropdown values fetched from backend template data.');
        $dv->setFormula1('=' . $listRange);

        $sheet->setDataValidation($targetRange, $dv);
    }

    private function writeList(Worksheet $sheet, string $column, array $values): void
    {
        $values = array_values(array_filter($values, fn ($v) => trim((string) $v) !== ''));
        if (empty($values)) {
            $values = [''];
        }

        foreach ($values as $index => $value) {
            $sheet->setCellValue($column . ($index + 2), (string) $value);
        }
    }
}
