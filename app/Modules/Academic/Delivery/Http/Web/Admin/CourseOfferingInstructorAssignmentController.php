<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Delivery\Http\Requests\CourseDelivery\CheckCourseOfferingInstructorAssignmentsRequest;
use App\Modules\Academic\Delivery\Queries\CheckCourseOfferingInstructorAssignmentsQuery;
use Illuminate\Http\JsonResponse;

final class CourseOfferingInstructorAssignmentController extends Controller
{
    public function __invoke(CheckCourseOfferingInstructorAssignmentsRequest $request, CheckCourseOfferingInstructorAssignmentsQuery $query): JsonResponse
    {
        return ApiResponse::success($query->handle((int) app('campus')->id, $request->integer('semester_id')));
    }
}
