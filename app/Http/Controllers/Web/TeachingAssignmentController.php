<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignLecturerRequest;
use App\Http\Requests\ConflictCheckRequest;
use App\Http\Requests\ExportTeachingAssignmentsRequest;
use App\Http\Resources\TeachingAssignmentResource;
use App\Http\Resources\AvailableLecturerResource;
use App\Services\TeachingAssignmentService;
use App\Constants\TeachingAssignmentRoutes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TeachingAssignmentController extends Controller
{
    public function __construct(
        private TeachingAssignmentService $teachingAssignmentService
    ) {
        // $this->middleware('can:view_teaching_assignment')->only(['index']);
        // $this->middleware('can:assign_lecturer')->only(['assign']);
        // $this->middleware('can:unassign_lecturer')->only(['unassign']);
        // $this->middleware('can:export_teaching_assignment')->only(['export']);
        // $this->middleware('can:manage_teaching_assignment')->only(['availableLecturers', 'checkConflicts']);
    }

    /**
     * Display the teaching assignments index page
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        // If this is an API request, return JSON data
        if ($request->expectsJson()) {
            try {
                $filters = $request->only([
                    'semester_id',
                    'faculty',
                    'department',
                    'assignment_status',
                    'search',
                    'per_page'
                ]);

                $assignments = $this->teachingAssignmentService->getAssignments($filters);

                return response()->json([
                    'data' => TeachingAssignmentResource::collection($assignments->items()),
                    'meta' => [
                        'current_page' => $assignments->currentPage(),
                        'last_page' => $assignments->lastPage(),
                        'per_page' => $assignments->perPage(),
                        'total' => $assignments->total(),
                    ]
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to fetch teaching assignments', [
                    'error' => $e->getMessage(),
                    'filters' => $request->all()
                ]);

                return response()->json([
                    'message' => 'Failed to fetch teaching assignments',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        // Return Inertia page for web requests
        return Inertia::render('teaching-assignments/Index', [
            'filters' => $request->only(['semester_id', 'faculty', 'department', 'search']),
        ]);
    }

    /**
     * Assign a lecturer to a course offering
     */
    public function assign(AssignLecturerRequest $request): JsonResponse
    {
        try {
            $success = $this->teachingAssignmentService->assignLecturer(
                $request->validated('course_offering_id'),
                $request->validated('lecturer_id')
            );

            if ($success) {
                return response()->json([
                    'message' => 'Lecturer assigned successfully',
                    'success' => true
                ]);
            }

            return response()->json([
                'message' => 'Failed to assign lecturer',
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to assign lecturer', [
                'error' => $e->getMessage(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'success' => false
            ], 422);
        }
    }

    /**
     * Remove lecturer assignment from a course offering
     */
    public function unassign(int $courseOfferingId): JsonResponse
    {
        try {
            $success = $this->teachingAssignmentService->unassignLecturer($courseOfferingId);

            if ($success) {
                return response()->json([
                    'message' => 'Lecturer unassigned successfully',
                    'success' => true
                ]);
            }

            return response()->json([
                'message' => 'Failed to unassign lecturer',
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to unassign lecturer', [
                'error' => $e->getMessage(),
                'course_offering_id' => $courseOfferingId
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'success' => false
            ], 422);
        }
    }

    /**
     * Get available lecturers for a course offering
     */
    public function availableLecturers(int $courseOfferingId, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['faculty', 'department', 'search']);
            $lecturers = $this->teachingAssignmentService->getAvailableLecturers($courseOfferingId, $filters);

            return response()->json([
                'data' => AvailableLecturerResource::collection($lecturers),
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch available lecturers', [
                'error' => $e->getMessage(),
                'course_offering_id' => $courseOfferingId,
                'filters' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to fetch available lecturers',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Check for scheduling conflicts
     */
    public function checkConflicts(ConflictCheckRequest $request): JsonResponse
    {
        try {
            $conflicts = $this->teachingAssignmentService->checkScheduleConflicts(
                $request->validated('lecturer_id'),
                $request->validated('course_offering_id')
            );

            return response()->json([
                'conflicts' => $conflicts,
                'has_conflicts' => !empty($conflicts),
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check conflicts', [
                'error' => $e->getMessage(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'message' => 'Failed to check conflicts',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Export teaching assignments
     */
    public function export(ExportTeachingAssignmentsRequest $request): Response
    {
        try {
            $format = $request->validated('format');
            $filters = $request->getFilters();

            $filePath = $this->teachingAssignmentService->exportAssignments($filters, $format);

            $fileName = 'teaching-assignments-' . now()->format('Y-m-d-H-i-s') . '.' . $format;

            return response()->download($filePath, $fileName)->deleteFileAfterSend();
        } catch (\Exception $e) {
            Log::error('Failed to export teaching assignments', [
                'error' => $e->getMessage(),
                'request_data' => $request->validated()
            ]);

            return response([
                'message' => 'Failed to export teaching assignments: ' . $e->getMessage()
            ], 500);
        }
    }
}
