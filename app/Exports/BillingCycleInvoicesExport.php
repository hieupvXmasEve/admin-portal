<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BillingCycleInvoicesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $invoices;

    protected string $billingCycleName;

    protected array $filters;

    public function __construct(Collection $invoices, string $billingCycleName, array $filters = [])
    {
        $this->invoices = $invoices;
        $this->billingCycleName = $billingCycleName;
        $this->filters = $filters;
    }

    /**
     * Return the collection of invoices to export
     */
    public function collection(): Collection
    {
        return $this->invoices;
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        return [
            'Invoice Number',
            'Student ID',
            'Student Name',
            'Campus',
            'Total Amount (VND)',
            'Paid Amount (VND)',
            'Outstanding Amount (VND)',
            'Status',
            'Due Date',
            'Created Date',
        ];
    }

    /**
     * Map each invoice to the export format
     */
    public function map($invoice): array
    {
        $student = $invoice->student;
        $studentId = $student?->student_id ?? 'N/A';
        $studentName = $student?->full_name ?? 'Unknown';
        $campusName = $student?->campus?->name ?? 'N/A';
        $totalAmount = $invoice->total_amount ?? 0;
        $paidAmount = $invoice->paid_amount ?? 0;
        $outstandingAmount = $totalAmount - $paidAmount;

        return [
            $invoice->invoice_number ?? 'N/A',
            $studentId,
            $studentName,
            $campusName,
            $totalAmount,
            $paidAmount,
            $outstandingAmount,
            ucfirst($invoice->status ?? 'N/A'),
            $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d') : 'N/A',
            $invoice->created_at ? $invoice->created_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->invoices->count() + 1;

        return [
            // Style the header row
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
            // Style all data rows
            "A2:J{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
            ],
            // Right-align currency columns
            "E2:G{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                ],
            ],
        ];
    }

    /**
     * Set the worksheet title
     */
    public function title(): string
    {
        return 'Invoices';
    }
}
