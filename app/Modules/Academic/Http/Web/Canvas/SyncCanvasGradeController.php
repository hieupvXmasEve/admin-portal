<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Canvas;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\CanvasGradeSyncService;
use App\Modules\Academic\Http\Requests\Canvas\SyncCourseGradesRequest;
use Illuminate\Http\JsonResponse;

class SyncCanvasGradeController extends Controller
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
            // Canvas is re-fetched here, never the preview payload — apply is
            // always a fresh read from Canvas at write time (ADR 0014).
            $result = $this->gradeSyncService->syncCourseGrades($mapping, $request->validated('student_ids'));

            return ApiResponse::success($result);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to sync Canvas grades: '.$e->getMessage(), [], 500);
        }
    }
}
