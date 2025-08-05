<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class GradeTemplateExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected AssessmentComponentDetail $assessmentComponentDetail;
    protected CourseOffering $courseOffering;
    protected Collection $studentsWithScores;

    public function __construct(AssessmentComponentDetail $assessmentComponentDetail, CourseOffering $courseOffering, Collection $studentsWithScores)
    {
        $this->assessmentComponentDetail = $assessmentComponentDetail;
        $this->courseOffering = $courseOffering;
        $this->studentsWithScores = $studentsWithScores;
    }

    /**
     * Return the collection of students with their current scores
     */
    public function collection(): Collection
    {
        return $this->studentsWithScores;
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        return [
            'Student ID',
            'Student Name',
            'Email',
            'Points Earned',
            'Percentage Score',
            'Letter Grade',
            'Status',
            'Score Status',
            'Instructor Feedback',
            'Bonus Points',
            'Bonus Reason',
            'Late Penalty Applied',
            'Submission Status',
            'Private Notes',
            // Metadata columns for validation (hidden/protected)
            'Assessment Detail ID',
            'Max Points',
            'Current Score ID'
        ];
    }

    /**
     * Map each student record to the Excel row format
     */
    public function map($student): array
    {
        $score = $student->scores->first(); // Current score for this assessment detail

        return [
            $student->student_code,
            $student->full_name,
            $student->email,
            $score?->points_earned ?? 0,
            $score?->percentage_score ?? 0,
            $score?->letter_grade ?? '',
            $score?->status ?? 'not_submitted',
            $score?->score_status ?? 'draft',
            $score?->instructor_feedback ?? '',
            $score?->bonus_points ?? 0,
            $score?->bonus_reason ?? '',
            $score?->late_penalty_applied ?? 0,
            $score?->status ?? 'not_submitted',
            $score?->private_notes ?? '',
            // Metadata
            $this->assessmentComponentDetail->id,
            $this->assessmentComponentDetail->max_points ?? 100,
            $score?->id ?? null
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row styling
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
            ],
            // Data rows styling
            'A:Q' => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15, // Student ID
            'B' => 25, // Student Name
            'C' => 30, // Email
            'D' => 15, // Points Earned
            'E' => 18, // Percentage Score
            'F' => 15, // Letter Grade
            'G' => 15, // Status
            'H' => 15, // Score Status
            'I' => 40, // Instructor Feedback
            'J' => 15, // Bonus Points
            'K' => 25, // Bonus Reason
            'L' => 18, // Late Penalty Applied
            'M' => 18, // Submission Status
            'N' => 30, // Private Notes
            'O' => 20, // Assessment Detail ID (hidden)
            'P' => 15, // Max Points (hidden)
            'Q' => 18, // Current Score ID (hidden)
        ];
    }

    /**
     * Set the worksheet title
     */
    public function title(): string
    {
        $assessmentName = str_replace(['/', '\\', '?', '*', '[', ']'], '_', $this->assessmentComponentDetail->name);
        return substr("Grades_{$assessmentName}", 0, 31); // Excel sheet name limit
    }

    /**
     * Register events for additional formatting
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Hide metadata columns (O, P, Q)
                $sheet->getColumnDimension('O')->setVisible(false);
                $sheet->getColumnDimension('P')->setVisible(false);
                $sheet->getColumnDimension('Q')->setVisible(false);

                // Freeze the header row
                $sheet->freezePane('A2');

                // Add data validation for specific columns
                $this->addDataValidation($sheet);

                // Add instructions/notes
                $this->addInstructions($sheet);
            },
        ];
    }

    /**
     * Add data validation to specific columns
     */
    protected function addDataValidation(Worksheet $sheet): void
    {
        $lastRow = $this->studentsWithScores->count() + 1;

        // Letter Grade validation
        $letterGradeValidation = $sheet->getCell('F2')->getDataValidation();
        $letterGradeValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $letterGradeValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
        $letterGradeValidation->setAllowBlank(true);
        $letterGradeValidation->setShowInputMessage(true);
        $letterGradeValidation->setShowErrorMessage(true);
        $letterGradeValidation->setShowDropDown(true);
        $letterGradeValidation->setErrorTitle('Invalid Grade');
        $letterGradeValidation->setError('Please select a valid letter grade');
        $letterGradeValidation->setPromptTitle('Letter Grade');
        $letterGradeValidation->setPrompt('Select a letter grade from the dropdown');
        $letterGradeValidation->setFormula1('"A+,A,A-,B+,B,B-,C+,C,C-,D+,D,F,I,W,P,NP"');

        // Apply to all rows
        $sheet->setDataValidation("F2:F{$lastRow}", $letterGradeValidation);

        // Status validation
        $statusValidation = $sheet->getCell('G2')->getDataValidation();
        $statusValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $statusValidation->setAllowBlank(false);
        $statusValidation->setShowDropDown(true);
        $statusValidation->setFormula1('"not_submitted,submitted,grading,graded,returned"');
        $sheet->setDataValidation("G2:G{$lastRow}", $statusValidation);

        // Score Status validation
        $scoreStatusValidation = $sheet->getCell('H2')->getDataValidation();
        $scoreStatusValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $scoreStatusValidation->setAllowBlank(false);
        $scoreStatusValidation->setShowDropDown(true);
        $scoreStatusValidation->setFormula1('"draft,provisional,final"');
        $sheet->setDataValidation("H2:H{$lastRow}", $scoreStatusValidation);
    }

    /**
     * Add instructions to the worksheet
     */
    protected function addInstructions(Worksheet $sheet): void
    {
        $lastRow = $this->studentsWithScores->count() + 3;

        // Add instructions below the data
        $sheet->setCellValue("A{$lastRow}", 'INSTRUCTIONS:');
        $sheet->getStyle("A{$lastRow}")->getFont()->setBold(true);

        $instructions = [
            '1. Do not modify Student ID, Student Name, or Email columns',
            '2. Points Earned should not exceed Max Points (' . ($this->assessmentComponentDetail->max_points ?? 100) . ')',
            '3. Percentage Score will be calculated automatically if Points Earned is provided',
            '4. Use the dropdown menus for Letter Grade, Status, and Score Status',
            '5. Bonus Points and Late Penalty Applied should be positive numbers',
            '6. Do not modify or unhide the hidden columns (Assessment Detail ID, Max Points, Current Score ID)',
            '7. Save the file and upload it back to the system for import'
        ];

        foreach ($instructions as $index => $instruction) {
            $row = $lastRow + $index + 1;
            $sheet->setCellValue("A{$row}", $instruction);
        }
    }
}
