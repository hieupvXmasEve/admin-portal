<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TeachingAssignmentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected Collection $assignments;
    protected array $filters;

    public function __construct(Collection $assignments, array $filters = [])
    {
        $this->assignments = $assignments;
        $this->filters = $filters;
    }

    /**
     * Return the collection of assignments to export
     */
    public function collection(): Collection
    {
        return $this->assignments;
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        return [
            'Semester',
            'Unit Code',
            'Unit Name',
            'Section',
            'Lecturer Name',
            'Lecturer Employee ID',
            'Department',
            'Faculty',
            'Schedule Days',
            'Start Time',
            'End Time',
            'Location',
            'Delivery Mode',
            'Current Enrollment',
            'Max Capacity',
            'Waitlist Current',
            'Waitlist Capacity',
            'Enrollment Status',
            'Assignment Status',
            'Assignment Priority',
            'Campus',
            'Registration Start',
            'Registration End',
            'Special Requirements',
            'Notes'
        ];
    }

    /**
     * Map each assignment to the export format
     */
    public function map($assignment): array
    {
        return [
            $assignment->semester?->name ?? 'N/A',
            $assignment->curriculumUnit?->unit?->code ?? 'N/A',
            $assignment->curriculumUnit?->unit?->name ?? 'N/A',
            $assignment->section_code ?? 'N/A',
            $assignment->lecture?->display_name ?? 'Unassigned',
            $assignment->lecture?->employee_id ?? 'N/A',
            $assignment->lecture?->department ?? 'N/A',
            $assignment->lecture?->faculty ?? 'N/A',
            is_array($assignment->schedule_days) ? implode(', ', $assignment->schedule_days) : 'N/A',
            $assignment->schedule_time_start?->format('H:i') ?? 'N/A',
            $assignment->schedule_time_end?->format('H:i') ?? 'N/A',
            $assignment->location ?? 'N/A',
            $assignment->delivery_mode ?? 'N/A',
            $assignment->current_enrollment ?? 0,
            $assignment->max_capacity ?? 0,
            $assignment->current_waitlist ?? 0,
            $assignment->waitlist_capacity ?? 0,
            $assignment->enrollment_status ?? 'N/A',
            $assignment->getInstructorAssignmentStatusLabel(),
            $assignment->getAssignmentPriority(),
            $assignment->campus?->name ?? 'N/A',
            $assignment->registration_start_date?->format('Y-m-d') ?? 'N/A',
            $assignment->registration_end_date?->format('Y-m-d') ?? 'N/A',
            $assignment->special_requirements ?? 'N/A',
            $assignment->notes ?? 'N/A'
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Style the header row
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
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
            'A2:Y' . ($this->assignments->count() + 1) => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    /**
     * Define column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15, // Semester
            'B' => 12, // Unit Code
            'C' => 30, // Unit Name
            'D' => 10, // Section
            'E' => 25, // Lecturer Name
            'F' => 15, // Employee ID
            'G' => 20, // Department
            'H' => 20, // Faculty
            'I' => 20, // Schedule Days
            'J' => 12, // Start Time
            'K' => 12, // End Time
            'L' => 15, // Location
            'M' => 15, // Delivery Mode
            'N' => 12, // Current Enrollment
            'O' => 12, // Max Capacity
            'P' => 12, // Waitlist Current
            'Q' => 12, // Waitlist Capacity
            'R' => 15, // Enrollment Status
            'S' => 20, // Assignment Status
            'T' => 15, // Assignment Priority
            'U' => 15, // Campus
            'V' => 15, // Registration Start
            'W' => 15, // Registration End
            'X' => 25, // Special Requirements
            'Y' => 25, // Notes
        ];
    }

    /**
     * Set the worksheet title
     */
    public function title(): string
    {
        $title = 'Teaching Assignments';
        
        if (!empty($this->filters['semester_id'])) {
            $title .= ' - Semester ' . $this->filters['semester_id'];
        }
        
        if (!empty($this->filters['campus_id'])) {
            $title .= ' - Campus ' . $this->filters['campus_id'];
        }
        
        return $title;
    }
}
