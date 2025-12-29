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

class CourseOfferingStatisticsExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    protected array $statistics;
    protected array $attendanceGrid;

    public function __construct(array $attendanceGrid, array $statistics)
    {
        $this->attendanceGrid = $attendanceGrid;
        $this->statistics = $statistics;
    }

    public function array(): array
    {
        $rows = [];

        // Course Information Header
        $rows[] = ['Course Statistics Export'];
        $rows[] = ['Course Code:', $this->statistics['course_code']];
        $rows[] = ['Course Name:', $this->statistics['course_name']];
        $rows[] = ['Section:', $this->statistics['section_code']];
        $rows[] = ['Semester:', $this->statistics['semester']];
        $rows[] = ['Instructor:', $this->statistics['instructor_name'] ?? 'N/A'];
        $rows[] = ['Total Students:', $this->statistics['total_students']];
        $rows[] = ['Total Sessions:', $this->statistics['total_sessions']];
        $rows[] = ['Allowed Absences:', $this->statistics['allowed_absences']];
        $rows[] = ['Students Exceeded:', $this->statistics['students_absent_exceeded'] ?? 0];
        $rows[] = ['']; // Spacer row (use [''] instead of [] to ensure it's not skipped)

        // Table Headings
        $rows[] = [
            'Student ID',
            'Full Name',
            'Present',
            'Absent (Used/Allowed)',
            'Late',
            'Attendance %',
            'Status',
            'Final %',
            'Grade Point',
            'Letter Grade',
            'Pass/Fail Status',
            'Override',
            'Override Reason'
        ];
        \Log::info($this->attendanceGrid);
        foreach ($this->attendanceGrid as $student) {
            $attendanceStatus = '';
            if (! $student['meets_attendance_requirement']) {
                $attendanceStatus = 'Failed';
            } elseif ($student['absences_remaining'] === 0) {
                $attendanceStatus = 'At Limit';
            } else {
                $attendanceStatus = 'Pass ('.$student['absences_remaining'].' left)';
            }

            $rows[] = [
                $student['student_id'],
                $student['full_name'],
                $student['total_present'],
                $student['total_absences'] . '/' . $student['allowed_absences'],
                $student['total_late'],
                number_format((float) $student['attendance_percentage'], 1) . '%',
                $attendanceStatus,
                $student['final_percentage'] . '%',
                $student['grade_points'],
                $student['letter_grade'],
                ($student['grade_status'] !== 'final' && $student['override_pass'] === null) ? '-' : ($student['is_passed'] ? 'PASS' : 'FAIL'),
                $student['override_pass'] && $student['override_reason'] ? 'Pass' : '-',
                $student['override_reason'] ?? '-'
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        // Style main title
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB'],
            ],
        ]);

        // Bold labels
        $sheet->getStyle('A2:A10')->applyFromArray([
            'font' => ['bold' => true],
        ]);

        $headerRowNumber = 12;

        // Style table headers
        $sheet->getStyle("A{$headerRowNumber}:M{$headerRowNumber}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4A5568'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Highlight attendance failures
        $firstDataRow = $headerRowNumber + 1;
        $lastDataRow = $firstDataRow + count($this->attendanceGrid) - 1;

        for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
            $studentIndex = $row - $firstDataRow;
            if (isset($this->attendanceGrid[$studentIndex]) && ! $this->attendanceGrid[$studentIndex]['meets_attendance_requirement']) {
                $sheet->getStyle("A{$row}:M{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FEE2E2'],
                    ],
                ]);
            }
        }

        return [];
    }

    public function title(): string
    {
        return 'Course Statistics';
    }
}
