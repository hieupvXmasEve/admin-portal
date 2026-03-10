<?php

declare(strict_types=1);

namespace App\Modules\Finance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GenerateChargesPreviewExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, array{id: int, student_id: string, full_name: string, status: string, has_existing_charge: bool, estimated_amount: float, warning: ?string, breakdown?: array, will_create_invoice?: bool}>  $students
     */
    public function __construct(
        private readonly array $students
    ) {}

    public function array(): array
    {
        $rows = [];

        foreach ($this->students as $index => $student) {
            $breakdownText = '';
            if (! empty($student['breakdown'])) {
                $parts = [];
                foreach ($student['breakdown'] as $item) {
                    $amount = number_format((float) $item['amount']);
                    $parts[] = "{$item['label']}: {$amount}";
                }
                $breakdownText = implode('; ', $parts);
            }

            $statusLabel = $student['will_create_invoice']
                ? 'New Invoice'
                : ($student['has_existing_charge'] ? 'Update/Merge' : 'Skipped');

            $rows[] = [
                $index + 1,
                $student['student_id'] ?? '',
                $student['full_name'] ?? '',
                $student['student_type'] ?? $student['status'] ?? '',
                $breakdownText,
                $student['estimated_amount'] ?? 0,
                $statusLabel,
                $student['warning'] ?? '',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'STT',
            'Mã SV',
            'Họ tên',
            'Type',
            'Charge Breakdown',
            'Net Due (VND)',
            'Trạng thái',
            'Ghi chú',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return 'Preview Charges';
    }
}
