<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\AssessmentComponentDetailScore;
use App\Shared\Contracts\Academic\DTO\StudentHubAssessmentCourseEvidence;
use App\Shared\Contracts\Academic\DTO\StudentHubAssessmentScoreEvidence;
use App\Shared\Contracts\Academic\StudentHubAssessmentEvidenceReader;
use Illuminate\Support\Collection;

final class EloquentStudentHubAssessmentEvidenceReader implements StudentHubAssessmentEvidenceReader
{
    public function forStudent(int $studentId): array
    {
        return AssessmentComponentDetailScore::query()
            ->where('student_id', $studentId)
            ->with([
                'courseOffering.unit:id,name,code',
                'courseOffering.semester:id,name,code',
                'courseOffering.syllabusTemplate:id,grading_scheme',
                'assessmentComponentDetail.assessmentComponent:id,name,type,weight',
                'assessmentComponentDetail:id,assessment_component_id,name,description,due_date,max_points',
            ])
            ->orderByDesc('graded_at')
            ->get()
            ->groupBy('course_offering_id')
            ->map(static function (Collection $scores, int|string $courseOfferingId): StudentHubAssessmentCourseEvidence {
                $firstScore = $scores->first();
                $courseOffering = $firstScore?->courseOffering;

                return new StudentHubAssessmentCourseEvidence(
                    courseOfferingId: (int) $courseOfferingId,
                    courseName: $courseOffering?->unit?->name ?? 'N/A',
                    courseCode: $courseOffering?->unit?->code ?? 'N/A',
                    semesterName: $courseOffering?->semester?->name ?? 'N/A',
                    gradingType: $courseOffering?->grading_type,
                    gradingScheme: $courseOffering?->syllabusTemplate?->grading_scheme,
                    scores: $scores->map(static fn (AssessmentComponentDetailScore $score): StudentHubAssessmentScoreEvidence => new StudentHubAssessmentScoreEvidence(
                        id: (int) $score->id,
                        assessmentName: $score->assessmentComponentDetail?->name ?? 'N/A',
                        assessmentType: $score->assessmentComponentDetail?->assessmentComponent?->type ?? 'N/A',
                        dueDate: $score->assessmentComponentDetail?->due_date?->toDateString(),
                        maxPoints: $score->assessmentComponentDetail?->max_points === null ? null : (float) $score->assessmentComponentDetail->max_points,
                        pointsEarned: $score->points_earned === null ? null : (int) $score->points_earned,
                        percentageScore: $score->percentage_score === null ? null : (float) $score->percentage_score,
                        letterGrade: $score->letter_grade,
                        gpaPoints: $score->gpa_points === null ? null : (float) $score->gpa_points,
                        submittedAt: $score->submitted_at?->toIso8601String(),
                        gradedAt: $score->graded_at?->toIso8601String(),
                        isLate: (bool) $score->is_late,
                        status: $score->status,
                    ))->all(),
                );
            })
            ->values()
            ->all();
    }
}
