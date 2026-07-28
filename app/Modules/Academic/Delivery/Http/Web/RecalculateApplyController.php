<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\FinalizeCourseOfferingAction;
use App\Modules\Academic\Delivery\Http\Requests\RecalculateCourseOfferingRequest;
use App\Modules\Academic\Delivery\Support\CanvasGradeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies the Recalculate action (issue 11, ADR 0014): one transaction —
 * optional Canvas pull for the selected students (re-fetched from Canvas,
 * never the preview payload), then a full whole-offering recalculate.
 */
class RecalculateApplyController extends Controller
{
    public function __construct(private CanvasGradeSyncService $gradeSyncService) {}

    public function __invoke(RecalculateCourseOfferingRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        $pullStudentIds = $request->validated('pull_student_ids');
        $mapping = null;

        if ($pullStudentIds !== null) {
            $mapping = $courseOffering->canvasCourseMappings()->where('sync_status', 'mapped')->first();
            if (! $mapping) {
                return ApiResponse::error('This course offering has no mapped Canvas course.', [], 400);
            }
        }

        try {
            $result = DB::transaction(function () use ($courseOffering, $pullStudentIds, $mapping) {
                if ($pullStudentIds !== null) {
                    $this->gradeSyncService->syncCourseGrades($mapping, $pullStudentIds);
                }

                return FinalizeCourseOfferingAction::run(['course_offering_id' => $courseOffering->id, 'recalculate' => true]);
            });
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to recalculate course: '.$e->getMessage(), [], 500);
        }

        return ApiResponse::success($result);
    }
}
