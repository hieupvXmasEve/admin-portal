<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Canvas;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CanvasCourseMapping;
use App\Modules\Academic\Delivery\Http\Requests\Canvas\SyncCanvasAssignmentsRequest;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasAssignmentSyncService;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasSyllabusService;
use App\Modules\Academic\Delivery\Support\CanvasGradeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CanvasSyllabusController extends Controller
{
    public function __construct(
        private CanvasSyllabusService $syllabusService,
        private CanvasAssignmentSyncService $assignmentSyncService,
        private CanvasGradeSyncService $gradeSyncService
    ) {}

    /**
     * Get the local syllabus synchronization state for a mapped Canvas course.
     */
    public function getSyncSummary(CanvasCourseMapping $mapping): JsonResponse
    {
        try {
            return ApiResponse::success($this->syllabusService->getSyncSummary($mapping));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), status: 400, data: ['can_sync' => false]);
        }
    }

    /**
     * Get assignment sync summary with detailed group information
     */
    public function getAssignmentSyncSummary(CanvasCourseMapping $mapping): JsonResponse
    {
        try {
            $summary = $this->assignmentSyncService->getDetailedSyncSummary($mapping);

            return ApiResponse::success($summary);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), status: 400, data: ['can_sync' => false]);
        }
    }

    /**
     * Sync Canvas assignments to local database
     */
    public function syncAssignments(SyncCanvasAssignmentsRequest $request, CanvasCourseMapping $mapping): RedirectResponse
    {
        try {
            $selectedGroupIds = $request->validated('selected_group_ids', []);

            $result = $this->assignmentSyncService->syncAssignments($mapping, $selectedGroupIds);

            $message = "Successfully synced {$result['total_synced']} assignments ({$result['created']} created, {$result['updated']} updated)";

            if (isset($result['groups_removed']) && $result['groups_removed'] > 0) {
                $message .= ". Removed {$result['groups_removed']} unselected groups";
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to sync assignments: '.$e->getMessage()]);
        }
    }

    /**
     * Get grade sync summary
     */
    public function getGradeSyncSummary(CanvasCourseMapping $mapping): JsonResponse
    {
        try {
            $summary = $this->gradeSyncService->getGradeSyncSummary($mapping);

            return ApiResponse::success($summary);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), status: 400, data: ['can_sync' => false]);
        }
    }

    /**
     * Sync Canvas grades for all students
     */
    public function syncGrades(CanvasCourseMapping $mapping): JsonResponse
    {
        try {
            $result = $this->gradeSyncService->syncCourseGrades($mapping);

            return ApiResponse::success($result);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), status: 400);
        }
    }
}
