<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Delivery\Actions\BulkRegisterCourseOfferingStudentsAction;
use App\Modules\Academic\Delivery\Actions\SearchCourseOfferingStudentsAction;
use App\Modules\Academic\Http\Requests\CourseDelivery\BulkRegisterCourseOfferingStudentsRequest;
use App\Modules\Academic\Http\Requests\CourseDelivery\SearchCourseOfferingStudentsRequest;
use Illuminate\Http\JsonResponse;

final class CourseOfferingRegistrationController extends Controller
{
    public function search(SearchCourseOfferingStudentsRequest $request, object $courseOffering): JsonResponse
    {
        $this->assertCampus($courseOffering);
        $codes = array_values(array_unique(array_filter(preg_split('/\s+/', trim($request->validated('student_ids'))) ?: [])));

        return ApiResponse::success(['students' => SearchCourseOfferingStudentsAction::run($courseOffering, $codes, (int) app('campus')->id)]);
    }

    public function bulkRegister(BulkRegisterCourseOfferingStudentsRequest $request, object $courseOffering): JsonResponse
    {
        $this->assertCampus($courseOffering);
        try {
            $result = BulkRegisterCourseOfferingStudentsAction::run($courseOffering, array_values(array_unique($request->validated('student_ids'))), (int) app('campus')->id);
            $message = "Successfully registered {$result['success_count']} student(s).";

            return ApiResponse::success($result, [], $message);
        } catch (\Throwable $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        }
    }

    private function assertCampus(object $courseOffering): void
    {
        if ((int) $courseOffering->campus_id !== (int) app('campus')->id) {
            abort(404);
        }
    }
}
