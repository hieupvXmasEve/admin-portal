<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\AcademicRecord;
use App\Modules\Academic\Support\Grading\Presenters\GradeDisplayPresenter;
use App\Shared\Contracts\Academic\DTO\StudentHubCourseOutcomeEvidence;
use App\Shared\Contracts\Academic\StudentHubCourseOutcomeEvidenceReader;

final class EloquentStudentHubCourseOutcomeEvidenceReader implements StudentHubCourseOutcomeEvidenceReader
{
    public function __construct(
        private readonly GradeDisplayPresenter $gradeDisplayPresenter,
    ) {}

    public function forStudent(int $studentId, array $courseOfferingIds = []): array
    {
        return AcademicRecord::query()
            ->where('student_id', $studentId)
            ->when($courseOfferingIds !== [], fn ($query) => $query->whereIn('course_offering_id', $courseOfferingIds))
            ->with([
                'courseOffering.syllabusTemplate:id,grading_scheme',
                'unit:id,code,name',
                'semester:id,name',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicRecord $record): StudentHubCourseOutcomeEvidence => new StudentHubCourseOutcomeEvidence(
                courseResultId: (int) $record->id,
                courseOfferingId: (int) $record->course_offering_id,
                unitId: (int) $record->unit_id,
                semesterId: $record->semester_id === null ? null : (int) $record->semester_id,
                finalPercentage: $record->final_percentage === null ? null : (float) $record->final_percentage,
                finalLetterGrade: $record->final_letter_grade,
                gradePoints: $record->grade_points === null ? null : (float) $record->grade_points,
                gradeStatus: $record->grade_status,
                completionStatus: $record->completion_status,
                isPassed: (bool) $record->is_passed,
                creditPoints: $record->credit_points === null ? null : (float) $record->credit_points,
                creditPointsEarned: $record->credit_points_earned === null ? null : (float) $record->credit_points_earned,
                attemptNumber: $record->attempt_number === null ? null : (int) $record->attempt_number,
                isRepeatCourse: (bool) $record->is_repeat_course,
                meetsAttendanceRequirement: $record->meets_attendance_requirement === null ? null : (bool) $record->meets_attendance_requirement,
                excludedFromGpa: (bool) $record->excluded_from_gpa,
                unitCode: $record->unit?->code,
                unitName: $record->unit?->name,
                semesterName: $record->semester?->name,
                gradeDisplay: empty($record->courseOffering?->syllabusTemplate?->grading_scheme) || empty($record->grade_breakdown)
                    ? null
                    : $this->gradeDisplayPresenter->present($record),
            ))
            ->all();
    }
}
