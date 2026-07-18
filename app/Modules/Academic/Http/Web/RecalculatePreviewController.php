<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use App\Modules\Academic\Delivery\Support\CanvasGradeSyncService;
use App\Modules\Academic\Http\Requests\RecalculateCourseOfferingRequest;
use App\Modules\Academic\Support\RecalculateCanvasOverrides;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Dry-run preview of the Recalculate action (issue 11, ADR 0014): shows the
 * per-student result changes, EGC level changes, and notification tier the
 * apply would produce, with no writes, events, or notifications. Optionally
 * simulates a "pull latest grades from Canvas first" step for the selected
 * students without touching the database.
 */
class RecalculatePreviewController extends Controller
{
    use RecalculateCanvasOverrides;

    public function __construct(private CanvasGradeSyncService $gradeSyncService) {}

    public function __invoke(RecalculateCourseOfferingRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        $pullStudentIds = $request->validated('pull_student_ids');
        $canvasPreview = null;

        if ($pullStudentIds !== null) {
            $mapping = $courseOffering->canvasCourseMappings()->where('sync_status', 'mapped')->first();
            if (! $mapping) {
                return ApiResponse::error('This course offering has no mapped Canvas course.', [], 400);
            }

            $canvasPreview = $this->gradeSyncService->previewCourseGrades($mapping, $pullStudentIds);
        }

        try {
            $result = MarkCourseOfferingCompletedAction::run(
                $courseOffering,
                recalculate: true,
                dryRun: true,
                finalPercentageOverrides: $this->courseTotalOverrides($canvasPreview),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 400);
        }

        return ApiResponse::success(array_merge($result, [
            'notification_tiers' => $result['egc_progression']['notification_tiers'] ?? $result['non_egc_result']['notification_tiers'] ?? [],
            'canvas_preview' => $canvasPreview,
        ]));
    }
}
