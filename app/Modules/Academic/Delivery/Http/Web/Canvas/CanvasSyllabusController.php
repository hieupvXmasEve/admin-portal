<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Canvas;

use App\Http\Controllers\Controller;
use App\Models\CanvasCourseMapping;
use App\Modules\Academic\Delivery\Support\CanvasGradeSyncService;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasAssignmentSyncService;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasSyllabusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CanvasSyllabusController extends Controller
{
    public function __construct(
        private CanvasSyllabusService $syllabusService,
        private CanvasAssignmentSyncService $assignmentSyncService,
        private CanvasGradeSyncService $gradeSyncService
    ) {}

    /**
     * Get assignment sync summary with detailed group information
     */
    public function getAssignmentSyncSummary(CanvasCourseMapping $mapping)
    {
        try {
            $summary = $this->assignmentSyncService->getDetailedSyncSummary($mapping);

            return response()->json($summary);
        } catch (\Exception $e) {
            return response()->json([
                'can_sync' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Sync Canvas assignments to local database
     */
    public function syncAssignments(Request $request, CanvasCourseMapping $mapping): RedirectResponse
    {
        try {
            $selectedGroupIds = $request->input('selected_group_ids', []);

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
    public function getGradeSyncSummary(CanvasCourseMapping $mapping)
    {
        try {
            $summary = $this->gradeSyncService->getGradeSyncSummary($mapping);

            return response()->json($summary);
        } catch (\Exception $e) {
            return response()->json([
                'can_sync' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Sync Canvas grades for all students
     */
    public function syncGrades(CanvasCourseMapping $mapping)
    {
        try {
            $result = $this->gradeSyncService->syncCourseGrades($mapping);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
