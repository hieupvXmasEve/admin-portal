<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Canvas;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\CanvasGradeSyncService;
use App\Modules\Academic\Delivery\Http\Requests\Canvas\SyncCourseGradesRequest;
use Illuminate\Http\JsonResponse;

class PreviewCanvasGradeSyncController extends Controller
{
    use ResolvesMappedCanvasCourse;

    public function __construct(private CanvasGradeSyncService $gradeSyncService) {}

    public function __invoke(SyncCourseGradesRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        $mapping = $this->resolveMappedCanvasCourse($courseOffering);
        if ($mapping instanceof JsonResponse) {
            return $mapping;
        }

        try {
            $result = $this->gradeSyncService->previewCourseGrades($mapping, $request->validated('student_ids'));

            return ApiResponse::success($result);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to preview Canvas grade sync: '.$e->getMessage(), [], 500);
        }
    }
}
