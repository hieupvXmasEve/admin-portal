<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StudentExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
        return $this->query->with([
            'campus:id,name,code',
            'program:id,name',
            'specialization:id,name', 
            'curriculumVersion:id,version_code',
            'statusChangedBy:id,full_name'
        ]);
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'Full Name',
            'Email',
            'Phone',
            'Date of Birth',
            'Gender',
            'Nationality',
            'National ID',
            'Address',
            'CCCD Address',
            'Campus',
            'Campus Code',
            'Program',
            'Specialization',
            'Curriculum Version',
            'Admission Date',
            'Expected Graduation Date',
            'Emergency Contact Name',
            'Emergency Contact Phone',
            'Emergency Contact Relationship',
            'High School Name',
            'High School Graduation Year',
            'Entrance Exam Score',
            'Status',
            'Academic Status',
            'Status Change Date',
            'Status Reason',
            'Status Changed By',
            'Admission Notes',
            'Last Login',
            'Email Verified',
            'Created Date',
            'Updated Date',
        ];
    }

    public function map($student): array
    {
        return [
            $student->student_id,
            $student->full_name,
            $student->email,
            $student->phone ?? 'N/A',
            $student->date_of_birth ? $student->date_of_birth->format('Y-m-d') : 'N/A',
            $student->gender ?? 'N/A',
            $student->nationality ?? 'N/A',
            $student->national_id ?? 'N/A',
            $student->address ?? 'N/A',
            $student->cccd_address ?? 'N/A',
            $student->campus?->name ?? 'N/A',
            $student->campus?->code ?? 'N/A',
            $student->program?->name ?? 'N/A',
            $student->specialization?->name ?? 'N/A',
            $student->curriculumVersion?->version_code ?? 'N/A',
            $student->admission_date ? $student->admission_date->format('Y-m-d') : 'N/A',
            $student->expected_graduation_date ? $student->expected_graduation_date->format('Y-m-d') : 'N/A',
            $student->emergency_contact_name ?? 'N/A',
            $student->emergency_contact_phone ?? 'N/A',
            $student->emergency_contact_relationship ?? 'N/A',
            $student->high_school_name ?? 'N/A',
            $student->high_school_graduation_year ?? 'N/A',
            $student->entrance_exam_score ?? 'N/A',
            ucfirst($student->status ?? 'N/A'),
            ucfirst($student->academic_status ?? 'N/A'),
            $student->status_change_date ? $student->status_change_date->format('Y-m-d') : 'N/A',
            $student->status_reason ?? 'N/A',
            $student->statusChangedBy?->full_name ?? 'N/A',
            $student->admission_notes ?? 'N/A',
            $student->last_login_at ? $student->last_login_at->format('Y-m-d H:i:s') : 'Never',
            $student->email_verified_at ? 'Verified' : 'Not Verified',
            $student->created_at->format('Y-m-d H:i:s'),
            $student->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
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
}
