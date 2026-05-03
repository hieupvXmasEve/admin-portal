<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\CourseOffering;
use App\Services\CourseStatisticsService;

/**
 * Extract assessment scores data for a course offering.
 *
 * Delegates to CourseStatisticsService::getAssessmentScoresGrid() to keep
 * logic in one place while enabling reuse from both:
 *   - CourseStatisticsController@assessmentScores (existing standalone page)
 *   - CourseOfferingController@show (new deferred prop)
 */
class GetCourseOfferingScoresQuery
{
    /**
     * Return the assessment scores grid data for the given course offering.
     *
     * @return array{
     *   course_offering: array{id: int, unit_id: int, semester_id: int, min_grade_threshold: float},
     *   statistics: array{course_code: string, course_name: string, section_code: string|null, semester: string, instructor_name: string|null, total_students: int, total_components: int, total_details: int, average_score: float},
     *   assessment_components: array<int, array{id: int, name: string, type: string, weight: float, details: array}>,
     *   assessment_details: array<int, array>,
     *   scores_grid: array<int, array>,
     * }
     */
    public static function handle(CourseOffering $courseOffering): array
    {
        /** @var CourseStatisticsService $service */
        $service = app(CourseStatisticsService::class);

        return $service->getAssessmentScoresGrid($courseOffering->id);
    }
}
