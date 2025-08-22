<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentApplicationTemplateExport implements FromCollection, WithHeadings, WithStyles
{
    protected array $templateData;

    public function __construct(array $templateData = [])
    {
        $this->templateData = $templateData;
    }

    public function collection()
    {
        // Convert associative array data to indexed arrays matching the headers
        $rows = [];
        foreach ($this->templateData as $rowData) {
            $rows[] = [
                $rowData['Full Name'] ?? '',
                $rowData['Student Code'] ?? '',
                $rowData['Email'] ?? '',
                $rowData['Gender'] ?? '',
                $rowData['Ethnicity'] ?? '',
                $rowData['Birth Day'] ?? '',
                $rowData['Birth Month'] ?? '',
                $rowData['Birth Year'] ?? '',
                $rowData['National ID'] ?? '',
                $rowData['Phone'] ?? '',
                $rowData['Address'] ?? '',
                $rowData['Parent Phone'] ?? '',
                $rowData['Parent Email'] ?? '',
                $rowData['Campus Code'] ?? '',
                $rowData['Intended Program'] ?? '',
                $rowData['Intended Specialization'] ?? '',
                $rowData['Intake'] ?? '',
                $rowData['English Test Type'] ?? '',
                $rowData['Listening'] ?? '',
                $rowData['Reading'] ?? '',
                $rowData['Writing'] ?? '',
                $rowData['Speaking'] ?? '',
                $rowData['Overall'] ?? '',
                $rowData['International'] ?? '',
                $rowData['Status'] ?? '',
            ];
        }
        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'Full Name',
            'Student Code',
            'Email',
            'Gender',
            'Ethnicity',
            'Birth Day',
            'Birth Month',
            'Birth Year',
            'National ID',
            'Phone',
            'Address',
            'Parent Phone',
            'Parent Email',
            'Campus Code',
            'Intended Program',
            'Intended Specialization',
            'Intake',
            'English Test Type',
            'Listening',
            'Reading',
            'Writing',
            'Speaking',
            'Overall',
            'International',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the header row
            1 => [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FFE2E2E2',
                    ],
                ],
            ],
        ];
    }
}