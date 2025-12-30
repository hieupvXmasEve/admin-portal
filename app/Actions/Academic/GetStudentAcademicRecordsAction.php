<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\GpaCalculation;
use App\Models\Student;

class GetStudentAcademicRecordsAction
{
    /**
     * Get finalized GPA records for a student.
     */
    public function execute(Student $student): array
    {
        $records = GpaCalculation::with('semester')
            ->where('student_id', $student->id)
            ->where('is_finalized', true)
            ->orderBy('semester_id', 'desc')
            ->get();

        $currentGpa = GpaCalculation::where('student_id', $student->id)
            ->where('is_current', true)
            ->where('is_finalized', true)
            ->first();

        return [
            'summary' => [
                'latest_semester_gpa' => $currentGpa?->semester_gpa ?? 0.0,
                'cumulative_gpa' => $currentGpa?->cumulative_gpa ?? 0.0,
                'academic_standing' => $currentGpa?->academic_standing ?? 'N/A',
                'credits_earned' => $currentGpa?->cumulative_credit_points_earned ?? 0.0,
            ],
            'history' => $records->map(fn($record) => [
                'semester_name' => $record->semester?->name,
                'semester_gpa' => $record->semester_gpa,
                'cumulative_gpa' => $record->cumulative_gpa,
                'academic_standing' => $record->academic_standing,
                'credits_attempted' => $record->semester_credit_points,
                'credits_earned' => $record->semester_credit_points_earned,
                'finalized_at' => $record->finalized_at?->toIso8601String(),
            ]),
        ];
    }
}
