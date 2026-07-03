<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\CourseOffering;
use App\Services\CourseStatisticsService;

/**
 * Extract assessment scores data for a course offering.
 *
 * Delegates to CourseStatisticsService::getAssessmentScoresGrid(). Backs the
 * Course Offering Cockpit's scores tab (CourseOfferingController@show); the
 * old standalone Course Statistics assessment-scores page that used to call
 * this directly is retired (ADR 0013 phase C) and now only redirects there.
 */
class GetCourseOfferingScoresQuery
{
    /**
     * Return the assessment scores grid data for the given course offering.
     *
     * @return array{
     *   course_offering: array{id: int, unit_id: int, semester_id: int, min_grade_threshold: float},
     *   statistics: array{average_score: float},
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
