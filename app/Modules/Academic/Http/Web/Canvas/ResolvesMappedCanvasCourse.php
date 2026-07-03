<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Canvas;

use App\Http\Responses\ApiResponse;
use App\Models\CanvasCourseMapping;
use App\Models\CourseOffering;
use Illuminate\Http\JsonResponse;

/**
 * Shared guard for the two Canvas grade sync endpoints (issue 10): a
 * standalone sync only exists while the offering is not completed
 * (ADR 0014 — post-completion grade changes flow only through Recalculate)
 * and only when a `mapped` Canvas course mapping exists.
 */
trait ResolvesMappedCanvasCourse
{
    /**
     * @return CanvasCourseMapping|JsonResponse The mapping, or an error response to return as-is.
     */
    private function resolveMappedCanvasCourse(CourseOffering $courseOffering): CanvasCourseMapping|JsonResponse
    {
        if ($courseOffering->course_status === 'completed') {
            return ApiResponse::error(
                'Canvas grade sync is unavailable on a completed offering. Use Recalculate instead.',
                [],
                409
            );
        }

        $mapping = $courseOffering->canvasCourseMappings()
            ->where('sync_status', 'mapped')
            ->first();

        if (! $mapping) {
            return ApiResponse::error('This course offering has no mapped Canvas course.', [], 400);
        }

        return $mapping;
    }
}
