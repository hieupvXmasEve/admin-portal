<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Api;

use App\Actions\ClassSession\BulkDeleteClassSessionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassSession\BulkDeleteClassSessionsRequest;
use App\Http\Requests\GenerateClassSessionsRequest;
use App\Http\Resources\ClassSession\ClassSessionResource;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\ClassSessionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassSessionController extends Controller
{
    public function __construct(protected ClassSessionService $classSessionService) {}

    /**
     * Get class sessions for a course offering
     */
    public function index(CourseOffering $courseOffering): AnonymousResourceCollection
    {
        $sessions = $this->classSessionService->getClassSessions($courseOffering);

        return ClassSessionResource::collection($sessions);
    }

    /**
     * Auto-generate class sessions for a course offering
     */
    public function generate(GenerateClassSessionsRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        try {
            $validated = $request->validated();
            $roomId = $validated['room_id'];
            $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null;
            $weeklySchedule = $validated['weekly_schedule'];
            $excludedDates = $validated['excluded_dates'] ?? [];

            // Generate new sessions
            $sessions = $this->classSessionService->generateClassSessions(
                $courseOffering,
                $roomId,
                $startDate,
                $weeklySchedule,
                $excludedDates
            );

            return response()->json([
                'success' => true,
                'message' => 'Class sessions generated successfully',
                'data' => [
                    'sessions_count' => $sessions->count(),
                    'sessions' => ClassSessionResource::collection($sessions),
                ],
            ]);
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'already exist') ? 400 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Generate attendance records for all enrolled students in a class session
     */
    public function generateAttendance(ClassSession $classSession): JsonResponse
    {
        try {
            $result = $this->classSessionService->generateAttendanceForSession($classSession);

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate attendance: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete all class sessions for a course offering
     */
    public function destroy(CourseOffering $courseOffering): JsonResponse
    {
        try {
            $deleted = $this->classSessionService->deleteClassSessions($courseOffering);

            return response()->json([
                'success' => true,
                'message' => $deleted
                    ? 'Class sessions deleted successfully'
                    : 'No class sessions found to delete',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete class sessions: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new class session
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_offering_id' => 'required|exists:course_offerings,id',
            'session_title' => 'required|string|max:255',
            'session_description' => 'nullable|string',
            'session_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'session_type' => 'required|in:lecture,tutorial,practical,workshop,seminar,exam',
            'delivery_mode' => 'required|in:in_person,online,hybrid',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            'attendance_required' => 'boolean',
            'attendance_tracking_enabled' => 'boolean',
            'online_meeting_url' => 'nullable|url',
            'instructor_notes' => 'nullable|string',
            'lecture_id' => 'nullable|exists:lectures,id',
            'room_id' => 'nullable|exists:rooms,id',
        ]);

        try {
            // Check syllabus template total_sessions limit
            $courseOffering = CourseOffering::with('syllabusTemplate')->find($validated['course_offering_id']);

            if ($courseOffering && $courseOffering->syllabusTemplate && $courseOffering->syllabusTemplate->total_sessions) {
                $currentSessionsCount = ClassSession::where('course_offering_id', $courseOffering->id)->count();

                if ($currentSessionsCount >= $courseOffering->syllabusTemplate->total_sessions) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot create session. Course offering has reached the maximum of {$courseOffering->syllabusTemplate->total_sessions} sessions allowed by the syllabus template.",
                    ], 400);
                }
            }

            $classSession = $this->classSessionService->createClassSession($validated);

            return response()->json([
                'success' => true,
                'message' => 'Class session created successfully',
                'data' => $classSession,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create class session: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a single class session
     */
    public function destroySingle(ClassSession $classSession): JsonResponse
    {
        try {
            $deleted = $this->classSessionService->deleteClassSession($classSession);

            return response()->json([
                'success' => true,
                'message' => 'Class session deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete class session: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete multiple class sessions
     */
    public function bulkDestroy(BulkDeleteClassSessionsRequest $request, BulkDeleteClassSessionsAction $action): JsonResponse
    {
        try {
            $deletedCount = $action->execute($request->validated()['ids']);

            return response()->json([
                'success' => true,
                'message' => $deletedCount.' class sessions deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete class sessions: '.$e->getMessage(),
            ], 500);
        }
    }
}
