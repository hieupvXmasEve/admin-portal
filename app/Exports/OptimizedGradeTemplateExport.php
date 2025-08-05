<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Services\AssessmentGradeExcelService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Database\Eloquent\Collection;

class OptimizedGradeTemplateExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithTitle
{
    protected AssessmentComponentDetail $assessmentComponentDetail;
    protected CourseOffering $courseOffering;
    protected AssessmentGradeExcelService $service;

    public function __construct(AssessmentComponentDetail $assessmentComponentDetail, CourseOffering $courseOffering)
    {
        $this->assessmentComponentDetail = $assessmentComponentDetail;
        $this->courseOffering = $courseOffering;
        $this->service = new AssessmentGradeExcelService();
    }

    /**
     * Return collection with memory optimization
     */
    public function collection(): Collection
    {
        // Get the query for students
        $query = $this->service->getStudentsWithScores(
            $this->assessmentComponentDetail,
            $this->courseOffering
        );

        // Get all students in the class
        $students = $query->get();

        // Load scores for all students at once (more efficient than N+1 queries)
        $students->each(function ($student) {
            $student->current_score = $this->service->getStudentScore(
                $student,
                $this->assessmentComponentDetail,
                $this->courseOffering
            );
        });

        // If no students found, create a placeholder row
        if ($students->isEmpty()) {
            $emptyStudent = new \stdClass();
            $emptyStudent->student_code = 'No students enrolled';
            $emptyStudent->full_name = '';
            $emptyStudent->email = '';
            $emptyStudent->current_score = null;
            $students = collect([$emptyStudent]);
        }

        return $students;
    }

    /**
     * Simplified headings to reduce memory overhead
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
            'Instructor Feedback',
            'Bonus Points',
            'Late Penalty Applied',
            // Hidden metadata columns
            'Assessment Detail ID',
            'Max Points',
            'Current Score ID'
        ];
    }

    /**
     * Memory-efficient mapping
     */
    public function map($student): array
    {
        $score = $student->current_score;

        return [
            $student->student_code,
            $student->full_name ?? '',
            $student->email ?? '',
            $score?->points_earned ?? 0,
            $score?->percentage_score ?? 0,
            $score?->letter_grade ?? '',
            $score?->status ?? 'not_submitted',
            $score?->instructor_feedback ?? '',
            $score?->bonus_points ?? 0,
            $score?->late_penalty_applied ?? 0,
            // Metadata
            $this->assessmentComponentDetail->id,
            $this->assessmentComponentDetail->max_points ?? 100,
            $score?->id ?? null
        ];
    }

    /**
     * Optimized column widths
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
            'H' => 40, // Instructor Feedback
            'I' => 15, // Bonus Points
            'J' => 18, // Late Penalty Applied
            'K' => 20, // Assessment Detail ID (hidden)
            'L' => 15, // Max Points (hidden)
            'M' => 18, // Current Score ID (hidden)
        ];
    }

    /**
     * Simplified worksheet title
     */
    public function title(): string
    {
        $assessmentName = str_replace(['/', '\\', '?', '*', '[', ']'], '_', $this->assessmentComponentDetail->name);
        return substr("Grades_{$assessmentName}", 0, 31);
    }
}
