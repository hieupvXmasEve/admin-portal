<?php

declare(strict_types=1);

namespace App\Modules\Finance\Exports;

use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class BatchChargePreviewExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<BatchPreviewLine>  $lines
     */
    public function __construct(
        private readonly array $lines,
    ) {}

    /**
     * @return list<array<int, mixed>>
     */
    public function array(): array
    {
        return collect($this->lines)
            ->values()
            ->map(function (BatchPreviewLine $line, int $index): array {
                $display = $line->display;

                return [
                    $index + 1,
                    (string) ($display['student_id'] ?? ''),
                    (string) ($display['label'] ?? ''),
                    (string) ($display['program_name'] ?? ''),
                    $display['term_number'] ?? null,
                    $this->feeCategoryLabel((string) ($display['fee_category'] ?? '')),
                    $this->diffLabel((string) ($display['diff'] ?? '')),
                    (float) ($display['gross'] ?? 0),
                    (string) ($display['scholarship_name'] ?? ''),
                    $this->scholarshipTypeLabel($display['scholarship_type'] ?? null),
                    $display['scholarship_raw_value'] ?? null,
                    (float) ($display['scholarship_amount'] ?? 0),
                    implode(', ', (array) ($display['voucher_codes'] ?? [])),
                    (float) ($display['voucher_amount'] ?? 0),
                    (float) ($display['discount'] ?? 0),
                    (float) ($display['net'] ?? 0),
                    (float) ($display['unapplied_credit'] ?? 0),
                    (float) ($display['credit_offset_projected'] ?? 0),
                    (string) ($display['reason'] ?? ''),
                ];
            })
            ->all();
    }

    public function headings(): array
    {
        return [
            'STT',
            'Mã sinh viên',
            'Họ và tên',
            'Ngành',
            'Kỳ HP',
            'Loại phí',
            'Phân loại',
            'Học phí gốc',
            'Tên học bổng',
            'Loại học bổng',
            'Giá trị học bổng',
            'Số tiền học bổng',
            'Mã voucher',
            'Số tiền voucher',
            'Tổng giảm trừ',
            'Học phí phải thu',
            'Số dư khả dụng',
            'Dự kiến trừ số dư',
            'Lý do',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Học phí gốc
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Giá trị học bổng
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Số tiền học bổng
            'N' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Số tiền voucher
            'O' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Tổng giảm trừ
            'P' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Học phí phải thu
            'Q' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Số dư khả dụng
            'R' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Dự kiến trừ số dư
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Danh sách học phí';
    }

    private function feeCategoryLabel(string $category): string
    {
        return match ($category) {
            'major' => 'HP (Tuition)',
            'egc' => 'EGC',
            'non_academic' => 'Phí phi học vụ',
            default => $category,
        };
    }

    private function diffLabel(string $diff): string
    {
        return match ($diff) {
            'create' => 'Tạo mới',
            'update' => 'Cập nhật',
            'skip' => 'Bỏ qua',
            'warning' => 'Cảnh báo',
            default => $diff,
        };
    }

    private function scholarshipTypeLabel(mixed $type): string
    {
        return match ((string) $type) {
            'percentage' => 'Phần trăm',
            'fixed_amount' => 'Số tiền cố định',
            default => (string) ($type ?? ''),
        };
    }
}
