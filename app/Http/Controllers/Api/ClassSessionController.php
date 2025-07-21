<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassSession\ClassSessionResource;
use App\Models\CourseOffering;
use App\Services\ClassSessionService;
use Illuminate\Http\JsonResponse;
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
    public function generate(CourseOffering $courseOffering): JsonResponse
    {
        try {
            // Delete existing sessions first
            $this->classSessionService->deleteClassSessions($courseOffering);

            // Generate new sessions
            $sessions = $this->classSessionService->generateClassSessions($courseOffering);

            return response()->json([
                'success' => true,
                'message' => 'Class sessions generated successfully',
                'data' => [
                    'sessions_count' => $sessions->count(),
                    'sessions' => ClassSessionResource::collection($sessions),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate class sessions: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate attendance records for all enrolled students in a class session
     */
    public function generateAttendance(\App\Models\ClassSession $classSession): JsonResponse
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
}
