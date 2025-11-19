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

class AttendanceGridExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    protected array $attendanceGrid;

    protected array $sessions;

    protected array $statistics;

    public function __construct(array $attendanceGrid, array $sessions, array $statistics)
    {
        $this->attendanceGrid = $attendanceGrid;
        $this->sessions = $sessions;
        $this->statistics = $statistics;
    }

    public function array(): array
    {
        $rows = [];

        // Add course information header rows
        $rows[] = ['Course Information'];
        $rows[] = ['Course Code:', $this->statistics['course_code']];
        $rows[] = ['Course Name:', $this->statistics['course_name']];
        $rows[] = ['Section:', $this->statistics['section_code']];
        $rows[] = ['Semester:', $this->statistics['semester']];
        $rows[] = ['Instructor:', $this->statistics['instructor_name'] ?? 'N/A'];
        $rows[] = ['Total Students:', $this->statistics['total_students']];
        $rows[] = ['Total Sessions:', $this->statistics['total_sessions']];
        $rows[] = ['Allowed Absences:', $this->statistics['allowed_absences'].' (20% of sessions)'];
        $rows[] = ['Students Exceeded:', $this->statistics['students_absent_exceeded']];
        $rows[] = []; // Empty row separator

        // Add attendance table headings
        $rows[] = $this->getAttendanceTableHeadings();

        foreach ($this->attendanceGrid as $student) {
            $row = [
                $student['student_id'],
                $student['full_name'],
            ];

            // Add attendance status for each session
            foreach ($student['sessions'] as $sessionData) {
                $status = $this->getStatusLabel($sessionData['status']);
                if ($sessionData['check_in_time']) {
                    $status .= ' ('.$sessionData['check_in_time'].')';
                }
                if ($sessionData['minutes_late']) {
                    $status .= ' +'.$sessionData['minutes_late'].'m';
                }
                $row[] = $status;
            }

            // Add summary columns
            $row[] = $student['total_present'];
            $row[] = $student['total_absences'].'/'.$student['allowed_absences']; // Show X/Y format
            $row[] = $student['total_late'];
            $row[] = number_format((float) $student['attendance_percentage'], 1).'%';

            // Determine status with absences remaining info
            if (! $student['meets_attendance_requirement']) {
                $row[] = 'Failed';
            } elseif ($student['absences_remaining'] === 0) {
                $row[] = 'At Limit';
            } else {
                $row[] = 'Pass ('.$student['absences_remaining'].' left)';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        // No headings needed as we're handling them in array()
        return [];
    }

    protected function getAttendanceTableHeadings(): array
    {
        $headings = [
            'Student ID',
            'Full Name',
        ];

        // Add session headings
        foreach ($this->sessions as $session) {
            $headings[] = 'S'.$session['session_number'].' ('.$session['session_date'].')';
        }

        // Add summary headings
        $headings[] = 'Present';
        $headings[] = 'Absent (Used/Allowed)';
        $headings[] = 'Late';
        $headings[] = 'Attendance %';
        $headings[] = 'Status';

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        // Style course information header (row 1)
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '1F2937'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB'],
            ],
        ]);

        // Bold labels in course information section (column A, rows 2-10)
        $sheet->getStyle('A2:A10')->applyFromArray([
            'font' => ['bold' => true],
        ]);

        // Calculate attendance table header row (after course info + empty row + heading row)
        $headerRowNumber = 12; // Row 1-10 = course info, row 11 = empty, row 12 = table headers

        // Style attendance table header row
        $sheet->getStyle("{$headerRowNumber}:{$headerRowNumber}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
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
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        // Highlight students who exceeded absence limit
        $firstDataRow = $headerRowNumber + 1;
        $lastDataRow = $firstDataRow + count($this->attendanceGrid) - 1;

        for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
            $studentIndex = $row - $firstDataRow;
            if (isset($this->attendanceGrid[$studentIndex]) && ! $this->attendanceGrid[$studentIndex]['meets_attendance_requirement']) {
                $sheet->getStyle("A{$row}:ZZ{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FEE2E2'], // Light red background
                    ],
                ]);
            }
        }

        return [];
    }

    public function title(): string
    {
        return 'Attendance Grid';
    }

    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'present' => 'P',
            'absent' => 'A',
            'late' => 'L',
            'excused' => 'E',
            default => '-',
        };
    }
}
