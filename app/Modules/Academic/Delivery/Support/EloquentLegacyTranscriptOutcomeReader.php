<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\AcademicRecord;
use App\Shared\Contracts\Academic\DTO\LegacyTranscriptOutcome;
use App\Shared\Contracts\Academic\LegacyTranscriptOutcomeReader;

final class EloquentLegacyTranscriptOutcomeReader implements LegacyTranscriptOutcomeReader
{
    public function finalizedOutcomes(array $scope): array
    {
        return AcademicRecord::query()
            ->where('grade_status', 'final')
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->orderBy('id')
            ->get()
            ->map(static fn (AcademicRecord $record): LegacyTranscriptOutcome => new LegacyTranscriptOutcome(
                courseResultId: (int) $record->id,
                studentId: $record->student_id === null ? null : (int) $record->student_id,
                courseOfferingId: $record->course_offering_id === null ? null : (int) $record->course_offering_id,
                semesterId: $record->semester_id === null ? null : (int) $record->semester_id,
                unitId: $record->unit_id === null ? null : (int) $record->unit_id,
                programId: $record->program_id === null ? null : (int) $record->program_id,
                campusId: $record->campus_id === null ? null : (int) $record->campus_id,
                attemptNumber: $record->attempt_number === null ? null : (int) $record->attempt_number,
                finalPercentage: $record->final_percentage === null ? null : (float) $record->final_percentage,
                finalLetterGrade: $record->final_letter_grade,
                creditPoints: $record->credit_points === null ? null : (float) $record->credit_points,
                creditPointsEarned: $record->credit_points_earned === null ? null : (float) $record->credit_points_earned,
                qualityPoints: $record->quality_points === null ? null : (float) $record->quality_points,
                isPassed: $record->is_passed === null ? null : (bool) $record->is_passed,
                excludedFromGpa: $record->excluded_from_gpa === null ? null : (bool) $record->excluded_from_gpa,
                affectsAcademicStanding: $record->affects_academic_standing === null ? null : (bool) $record->affects_academic_standing,
                affectsGraduationRequirement: $record->affects_graduation_requirement === null ? null : (bool) $record->affects_graduation_requirement,
                satisfiesPrerequisite: $record->satisfies_prerequisite === null ? null : (bool) $record->satisfies_prerequisite,
                finalizedOn: $record->grade_finalized_date?->toDateString(),
            ))
            ->all();
    }
}
