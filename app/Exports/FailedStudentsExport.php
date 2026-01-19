<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FailedStudentsExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    protected array $failedStudents;

    public function __construct(array $failedStudents)
    {
        $this->failedStudents = $failedStudents;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->failedStudents as $student) {
            $rows[] = [
                $student['student_id'],
                $student['student_name'],
                $student['student_email'],
                $student['program_name'] ?? 'N/A',
                $student['campus_name'] ?? 'N/A',
                $student['unit_code'] ?? 'N/A',
                $student['unit_name'] ?? 'N/A',
                $student['course_offering_section'] ?? 'N/A',
                $student['lecturer_name'] ?? 'Unassigned',
                number_format((float) $student['final_percentage'], 2).'%',
                $student['final_letter_grade'] ?? 'F',
                number_format((float) $student['attendance_percentage'], 2).'%',
                $student['attempt_number'] ?? 1,
                // $student['retake_eligible'] ? 'Yes' : 'No',
                $student['semester_name'] ?? 'N/A',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'Student Name',
            'Email',
            'Program',
            'Campus',
            'Unit Code',
            'Unit Name',
            'Course Offering / Section',
            'Lecturer',
            'Final %',
            'Letter Grade',
            'Attendance %',
            'Attempt Number',
            // 'Retake Eligible',
            'Semester',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Failed Students';
    }
}
