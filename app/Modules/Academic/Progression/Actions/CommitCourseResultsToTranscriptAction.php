<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\CourseResultTranscriptWriter;
use App\Shared\Contracts\Academic\DTO\CourseResult;

final class CommitCourseResultsToTranscriptAction implements CourseResultTranscriptWriter
{
    /** @param list<CourseResult> $courseResults */
    public function commit(array $courseResults): void
    {
        foreach ($courseResults as $courseResult) {
            TranscriptEntry::query()->updateOrCreate(
                ['course_result_id' => $courseResult->courseResultId],
                [
                    'student_id' => $courseResult->studentId,
                    'course_offering_id' => $courseResult->courseOfferingId,
                    'semester_id' => $courseResult->semesterId,
                    'unit_id' => $courseResult->unitId,
                    'program_id' => $courseResult->programId,
                    'campus_id' => $courseResult->campusId,
                    'attempt_number' => $courseResult->attemptNumber,
                    'final_percentage' => $courseResult->finalPercentage,
                    'final_letter_grade' => $courseResult->finalLetterGrade,
                    'credit_points' => $courseResult->creditPoints,
                    'credit_points_earned' => $courseResult->creditPointsEarned,
                    'quality_points' => $courseResult->qualityPoints,
                    'is_passed' => $courseResult->isPassed,
                    'excluded_from_gpa' => $courseResult->excludedFromGpa,
                    'affects_academic_standing' => $courseResult->affectsAcademicStanding,
                    'affects_graduation_requirement' => $courseResult->affectsGraduationRequirement,
                    'satisfies_prerequisite' => $courseResult->satisfiesPrerequisite,
                    'finalized_at' => $courseResult->finalizedAt,
                ],
            );
        }
    }
}
