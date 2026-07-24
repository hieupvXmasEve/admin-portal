<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\GpaCalculation;
use App\Shared\Contracts\Academic\DTO\StudentAcademicRecords;
use App\Shared\Contracts\Academic\StudentAcademicRecordsReader;

final class GetStudentAcademicRecordsQuery implements StudentAcademicRecordsReader
{
    /**
     * @return array{
     *     summary: array{latest_semester_gpa: float|string, cumulative_gpa: float|string, academic_standing: string, credits_earned: float|string},
     *     history: list<array{semester_name: ?string, semester_gpa: float|string, cumulative_gpa: float|string, academic_standing: ?string, credits_attempted: float|string, credits_earned: float|string, finalized_at: ?string}>
     * }
     */
    public function forStudent(int $studentId): StudentAcademicRecords
    {
        $records = GpaCalculation::query()
            ->with('semester')
            ->where('student_id', $studentId)
            ->where('is_finalized', true)
            ->orderByDesc('semester_id')
            ->get();

        $currentGpa = GpaCalculation::query()
            ->where('student_id', $studentId)
            ->where('is_current', true)
            ->where('is_finalized', true)
            ->first();

        return new StudentAcademicRecords([
            'summary' => [
                'latest_semester_gpa' => $currentGpa?->semester_gpa ?? 0.0,
                'cumulative_gpa' => $currentGpa?->cumulative_gpa ?? 0.0,
                'academic_standing' => $currentGpa?->academic_standing ?? 'N/A',
                'credits_earned' => $currentGpa?->cumulative_credit_points_earned ?? 0.0,
            ],
            'history' => $records->map(static fn (GpaCalculation $record): array => [
                'semester_name' => $record->semester?->name,
                'semester_gpa' => $record->semester_gpa,
                'cumulative_gpa' => $record->cumulative_gpa,
                'academic_standing' => $record->academic_standing,
                'credits_attempted' => $record->semester_credit_points,
                'credits_earned' => $record->semester_credit_points_earned,
                'finalized_at' => $record->finalized_at?->toIso8601String(),
            ])->all(),
        ], $records->map(static fn (GpaCalculation $record): array => [
            'semester' => $record->semester?->name,
            'semester_code' => $record->semester?->code,
            'gpa' => (float) $record->semester_gpa,
            'credit_hours' => (float) $record->semester_credit_points_earned,
            'quality_points' => (float) $record->semester_quality_points,
            'academic_standing' => $record->academic_standing,
            'created_at' => $record->created_at?->toIso8601String(),
        ])->all());
    }
}
