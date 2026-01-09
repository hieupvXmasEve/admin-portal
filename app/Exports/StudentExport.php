<?php

namespace App\Exports;

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
            'curriculumVersion.curriculumUnits.unit:id,credit_points',
            'curriculumVersion.curriculumUnits.semester:id',
            'statusChangedBy:id,full_name',
            'intakeSemester:id,name,code',
            'scholarshipAward.scholarship:code,name',
        ]);
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'Full Name',
            'Date of Birth',
            'Gender',
            'Address',
            'CCCD Address',
            'Current Address Line',
            'Current Ward',
            'Current Province',
            'Current Country',
            'CCCD Address Line',
            'CCCD Ward',
            'CCCD Province',
            'CCCD Country',
            'Ethnicity',
            'Campus',
            'Campus Code',
            'Program',
            'Specialization',
            'Curriculum Version',
            'Intake Semester',
            'Intake GC',
            'Intake Course',
            'GC Starting Level',
            'GC Current Level',
            'GC to Course Transition Semester',
            // 'Admission Date',
            // 'Expected Graduation Date',
            // 'Emergency Contact Name',
            // 'Emergency Contact Phone',
            // 'Emergency Contact Relationship',
            // 'High School Name',
            // 'High School Graduation Year',
            // 'Entrance Exam Score',
            'Status',
            'Academic Status',
            // 'Status Change Date',
            // 'Status Reason',
            // 'Status Changed By',
            'Scholarship',
            'Total Credit',
            // 'Semester Credit',
            // 'Admission Notes',
            // 'Last Login',
            // 'Email Verified',
            // 'Created Date',
            // 'Updated Date',
        ];
    }

    public function map($student): array
    {
        // Calculate Total Credit: sum of all units in curriculum version
        $totalCredit = 0;
        if ($student->curriculumVersion && $student->curriculumVersion->curriculumUnits) {
            $totalCredit = $student->curriculumVersion->curriculumUnits->sum(function ($curriculumUnit) {
                return $curriculumUnit->unit?->credit_points ?? 0;
            });
        }

        // Calculate Semester Credit: sum of units in current active semester
        // $semesterCredit = 0;
        // $activeSemester = Semester::getActiveSemester();
        // if ($activeSemester && $student->curriculumVersion && $student->curriculumVersion->curriculumUnits) {
        //     $semesterCredit = $student->curriculumVersion->curriculumUnits
        //         ->where('semester_id', $activeSemester->id)
        //         ->sum(function ($curriculumUnit) {
        //             return $curriculumUnit->unit?->credit_points ?? 0;
        //         });
        // }

        // Get scholarship name
        $scholarship = $student->scholarshipAward?->scholarship?->name ?? '';

        return [
            $student->student_id,
            $student->full_name,
            $student->date_of_birth ? $student->date_of_birth->format('Y-m-d') : '',
            $student->gender ?? '',
            $student->address ?? '',
            $student->cccd_address ?? '',
            $student->current_address_line ?? '',
            $student->current_ward ?? '',
            $student->current_province ?? '',
            $student->current_country ?? '',
            $student->cccd_address_line ?? '',
            $student->cccd_ward ?? '',
            $student->cccd_province ?? '',
            $student->cccd_country ?? '',
            $student->ethnicity ?? '',
            $student->campus?->name ?? '',
            $student->campus?->code ?? '',
            $student->program?->name ?? '',
            $student->specialization?->name ?? '',
            $student->curriculumVersion?->version_code ?? '',
            $student->intakeSemester?->name ?? '',
            $student->intake_gc ?? '',
            $student->intake_course ?? '',
            $student->gc_starting_level ?? '',
            $student->gc_current_level ?? '',
            $student->gc_to_course_transition_semester ?? '',
            // $student->admission_date ? $student->admission_date->format('Y-m-d') : '',
            // $student->expected_graduation_date ? $student->expected_graduation_date->format('Y-m-d') : '',
            // $student->emergency_contact_name ?? '',
            // $student->emergency_contact_phone ?? '',
            // $student->emergency_contact_relationship ?? '',
            // $student->high_school_name ?? '',
            // $student->high_school_graduation_year ?? '',
            // $student->entrance_exam_score ?? '',
            ucfirst($student->status ?? ''),
            ucfirst($student->academic_status ?? ''),
            // $student->status_change_date ? $student->status_change_date->format('Y-m-d') : '',
            // $student->status_reason ?? '',
            // $student->statusChangedBy?->full_name ?? '',
            $scholarship,
            $totalCredit,
            // $semesterCredit,
            // $student->admission_notes ?? '',
            // $student->last_login_at ? $student->last_login_at->format('Y-m-d H:i:s') : 'Never',
            // $student->email_verified_at ? 'Verified' : 'Not Verified',
            // $student->created_at->format('Y-m-d H:i:s'),
            // $student->updated_at->format('Y-m-d H:i:s'),
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
