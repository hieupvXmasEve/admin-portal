<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingStatisticsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CourseOfferingStatisticsController extends Controller
{
    public function __invoke(Request $request, GetCourseOfferingStatisticsQuery $query): JsonResponse
    {
        $semesterId = $request->input('semester_id');
        $selectedSemesterId = $semesterId && $semesterId !== 'all' ? (int) $semesterId : null;

        return ApiResponse::success($query->handle((int) app('campus')->id, $selectedSemesterId));
    }
}
