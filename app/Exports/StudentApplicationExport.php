<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentApplicationExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected Builder $query;

    protected array $filters;

    public function __construct(Builder $query, array $filters = [])
    {
        $this->query = $query;
        $this->filters = $filters;
    }

    public function query()
    {
        return $this->query->with(['student' => function ($query) {
            $query->select('id', 'student_id', 'full_name');
        }]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Full Name',
            'Gender',
            'Ethnicity',
            'Date of Birth',
            'National ID',
            'Phone',
            'Email',
            'Address',
            'Health Information',
            'Campus',
            'Intended Program',
            'Intended Specialization',
            'Intake',
            'Exam Date',
            'English Test Type',
            'Listening',
            'Reading',
            'Writing',
            'Speaking',
            'Overall Score',
            'StudyLink Status',
            'English Qualifications',
            'SUT ID',
            'International Applicant',
            'Exception Units',
            'Application Status',
            'Student ID',
            'Student Name',
            'Created Date',
            'Updated Date',
        ];
    }

    public function map($application): array
    {
        return [
            $application->id,
            $application->full_name,
            $application->gender ?? 'N/A',
            $application->ethnicity ?? 'N/A',
            $this->formatBirthDate($application),
            $application->national_id ?? 'N/A',
            $application->phone ?? 'N/A',
            $application->email ?? 'N/A',
            $application->address ?? 'N/A',
            $application->health_information ?? 'N/A',
            $application->campus_code ?? 'N/A',
            $application->intended_program ?? 'N/A',
            $application->intended_specialization ?? 'N/A',
            $application->intake ?? 'N/A',
            $application->exam_date ? date('Y-m-d', strtotime($application->exam_date)) : 'N/A',
            $application->english_test_type ?? 'N/A',
            $application->listening ?? 'N/A',
            $application->reading ?? 'N/A',
            $application->writing ?? 'N/A',
            $application->speaking ?? 'N/A',
            $application->overall ?? 'N/A',
            $application->study_link_status ?? 'N/A',
            $application->english_qualifications ?? 'N/A',
            $application->sut_id ?? 'N/A',
            $application->is_international_applicant ? 'Yes' : 'No',
            $application->exception_units ?? 'N/A',
            ucfirst($application->status),
            $application->student ? $application->student->student_id : 'Not Converted',
            $application->student ? $application->student->full_name : 'N/A',
            $application->created_at ? date('Y-m-d H:i:s', strtotime($application->created_at)) : 'N/A',
            $application->updated_at ? date('Y-m-d H:i:s', strtotime($application->updated_at)) : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Freeze the header row
        $sheet->freezePane('A2');

        return [];
    }

    private function formatBirthDate($application): string
    {
        if (! $application->birth_day || ! $application->birth_month || ! $application->birth_year) {
            return 'N/A';
        }

        return sprintf(
            '%02d/%02d/%04d',
            $application->birth_day,
            $application->birth_month,
            $application->birth_year
        );
    }
}
