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
            $row[] = $student['total_absences'];
            $row[] = $student['total_late'];
            $row[] = number_format((float) $student['attendance_percentage'], 1).'%';
            $row[] = $student['meets_attendance_requirement'] ? 'Pass' : 'Fail';

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
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
        $headings[] = 'Absent';
        $headings[] = 'Late';
        $headings[] = 'Attendance %';
        $headings[] = 'Status';

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        // Style header row
        $sheet->getStyle('1:1')->applyFromArray([
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
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Highlight students who exceeded absence limit
        $rowCount = count($this->attendanceGrid) + 1;
        for ($row = 2; $row <= $rowCount; $row++) {
            $studentIndex = $row - 2;
            if (isset($this->attendanceGrid[$studentIndex]) && ! $this->attendanceGrid[$studentIndex]['meets_attendance_requirement']) {
                $sheet->getStyle("A{$row}:ZZ{$row}")->applyFromArray([
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
