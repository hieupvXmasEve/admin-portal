<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Shared\Contracts\Academic\CourseResultTranscriptCommitter;
use App\Shared\Contracts\Academic\CourseResultTranscriptWriter;
use App\Shared\Contracts\Academic\DTO\CourseResult;

final class CommitCourseResultsAndTranscriptEntriesAction implements CourseResultTranscriptCommitter
{
    public function commitForCourseOffering(int $courseOfferingId): void
    {
        self::run(['course_offering_id' => $courseOfferingId]);
    }

    /** @param array{course_offering_id:int} $data */
    public static function run(array $data): void
    {
        $courseOffering = CourseOffering::query()->findOrFail($data['course_offering_id']);
        $eligibleStudentIds = CourseRegistration::query()
            ->where('course_offering_id', $courseOffering->id)
            ->where('registration_status', 'completed')
            ->pluck('student_id');

        $courseResults = AcademicRecord::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $eligibleStudentIds)
            ->where('grade_status', 'final')
            ->get()
            ->map(static fn (AcademicRecord $record): CourseResult => new CourseResult(
                courseResultId: $record->id,
                studentId: $record->student_id,
                courseOfferingId: $record->course_offering_id,
                semesterId: $record->semester_id,
                unitId: $record->unit_id,
                programId: $record->program_id,
                campusId: $record->campus_id,
                attemptNumber: (int) ($record->attempt_number ?? 1),
                finalPercentage: (float) $record->final_percentage,
                finalLetterGrade: (string) $record->final_letter_grade,
                creditPoints: (float) $record->credit_points,
                creditPointsEarned: (float) $record->credit_points_earned,
                qualityPoints: (float) $record->quality_points,
                isPassed: (bool) $record->is_passed,
                excludedFromGpa: (bool) $record->excluded_from_gpa,
                affectsAcademicStanding: (bool) $record->affects_academic_standing,
                affectsGraduationRequirement: (bool) $record->affects_graduation_requirement,
                satisfiesPrerequisite: (bool) $record->satisfies_prerequisite,
                finalizedAt: $record->grade_finalized_date?->toIso8601String(),
            ))
            ->all();

        app(CourseResultTranscriptWriter::class)->commit($courseResults);
    }
}
