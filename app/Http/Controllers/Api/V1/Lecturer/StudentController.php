<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\StudentFilterRequest;
use App\Http\Requests\Api\V1\Lecturer\StudentNoteRequest;
use App\Http\Resources\Api\V1\Lecturer\StudentDetailResource;
use App\Http\Resources\Api\V1\Lecturer\StudentResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Lecturer\LecturerStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        protected LecturerStudentService $studentService
    ) {}

    /**
     * Get students for lecturer's courses
     */
    public function index(StudentFilterRequest $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->validated();
            $perPage = (int) $request->query('per_page', 15);
            $perPage = min(max($perPage, 5), 50);

            $students = $this->studentService->getStudents($lecturer, $filters, $perPage);

            return ApiResponse::paginated(
                $students->through(fn($student) => new StudentResource($student)),
                'Students retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve students');
        }
    }

    /**
     * Get detailed student information
     */
    public function show(Request $request, int $studentId): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $studentDetails = $this->studentService->getStudentDetails($lecturer, $studentId);

            if (! $studentDetails) {
                return ApiResponse::notFound('Student not found or access denied');
            }

            return ApiResponse::success(
                new StudentDetailResource($studentDetails),
                'Student details retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve student details');
        }
    }

    /**
     * Get student alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->only(['course_offering_id', 'priority', 'type']);

            $alerts = $this->studentService->getStudentAlerts($lecturer, $filters);

            return ApiResponse::success(
                $alerts,
                'Student alerts retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve student alerts');
        }
    }

    /**
     * Add note for student
     */
    public function addNote(StudentNoteRequest $request, int $studentId): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $noteData = $request->validated();

            $result = $this->studentService->addStudentNote($lecturer, $studentId, $noteData);

            return ApiResponse::success(
                $result,
                'Student note added successfully'
            );
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Student not found or access denied') {
                return ApiResponse::notFound($e->getMessage());
            }

            return ApiResponse::serverError('Failed to add student note');
        }
    }

    /**
     * Update student note
     */
    public function updateNote(StudentNoteRequest $request, int $studentId, int $noteId): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $updateData = $request->validated();

            $result = $this->studentService->updateStudentNote($lecturer, $noteId, $updateData);

            return ApiResponse::success(
                $result,
                'Student note updated successfully'
            );
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Note not found or access denied') {
                return ApiResponse::notFound($e->getMessage());
            }

            return ApiResponse::serverError('Failed to update student note');
        }
    }

    /**
     * Delete student note
     */
    public function deleteNote(Request $request, int $studentId, int $noteId): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $result = $this->studentService->deleteStudentNote($lecturer, $noteId);

            return ApiResponse::success(
                $result,
                'Student note deleted successfully'
            );
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Note not found or access denied') {
                return ApiResponse::notFound($e->getMessage());
            }

            return ApiResponse::serverError('Failed to delete student note');
        }
    }

    /**
     * Get student performance analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->only(['course_offering_id', 'semester_id', 'date_from', 'date_to']);

            $analytics = $this->studentService->getStudentPerformanceAnalytics($lecturer, $filters);

            return ApiResponse::success(
                $analytics,
                'Student performance analytics retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve student performance analytics');
        }
    }

    /**
     * Get students requiring attention
     */
    public function requiresAttention(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = array_merge(
                $request->only(['course_offering_id']),
                ['requires_attention' => true]
            );

            $students = $this->studentService->getStudents($lecturer, $filters, 50);

            return ApiResponse::success(
                StudentResource::collection($students->items()),
                'Students requiring attention retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve students requiring attention');
        }
    }

    /**
     * Get student summary for dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->only(['course_offering_id', 'semester_id']);

            // Get students for summary
            $students = $this->studentService->getStudents($lecturer, $filters, 1000);
            $alerts = $this->studentService->getStudentAlerts($lecturer, $filters);

            $summary = [
                'total_students' => $students->total(),
                'active_students' => $students->count(),
                'total_alerts' => count($alerts),
                'high_priority_alerts' => collect($alerts)->where('priority', 'high')->count(),
                'medium_priority_alerts' => collect($alerts)->where('priority', 'medium')->count(),
                'low_priority_alerts' => collect($alerts)->where('priority', 'low')->count(),
                'alert_breakdown' => [
                    'low_attendance' => collect($alerts)->where('type', 'low_attendance')->count(),
                    'consecutive_absence' => collect($alerts)->where('type', 'consecutive_absence')->count(),
                    'no_recent_attendance' => collect($alerts)->where('type', 'no_recent_attendance')->count(),
                ],
                'recent_alerts' => collect($alerts)->take(5)->values(),
            ];

            return ApiResponse::success(
                $summary,
                'Student summary retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve student summary');
        }
    }

    /**
     * Bulk actions on students
     */
    public function bulkActions(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $validated = $request->validate([
                'action' => 'required|string|in:add_note,send_notification,mark_for_follow_up',
                'student_ids' => 'required|array|min:1|max:50',
                'student_ids.*' => 'integer',
                'data' => 'required|array',
            ]);

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($validated['student_ids'] as $studentId) {
                try {
                    $result = $this->processBulkAction(
                        $lecturer,
                        $validated['action'],
                        $studentId,
                        $validated['data']
                    );

                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'success',
                        'result' => $result,
                    ];
                    $successCount++;
                } catch (\Exception $e) {
                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                    $errorCount++;
                }
            }

            return ApiResponse::success([
                'action' => $validated['action'],
                'total_processed' => count($validated['student_ids']),
                'successful_actions' => $successCount,
                'failed_actions' => $errorCount,
                'results' => $results,
            ], 'Bulk action completed');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to process bulk actions');
        }
    }

    /**
     * Process individual bulk action
     */
    protected function processBulkAction(
        $lecturer,
        string $action,
        int $studentId,
        array $data
    ): array {
        return match ($action) {
            'add_note' => $this->studentService->addStudentNote($lecturer, $studentId, $data),
            'send_notification' => $this->sendNotificationToStudent($lecturer, $studentId, $data),
            'mark_for_follow_up' => $this->markStudentForFollowUp($lecturer, $studentId, $data),
            default => throw new \Exception('Unknown action: ' . $action),
        };
    }

    /**
     * Send notification to student (placeholder)
     */
    protected function sendNotificationToStudent($lecturer, int $studentId, array $data): array
    {
        // Implementation would integrate with notification system
        return ['message' => 'Notification sent successfully'];
    }

    /**
     * Mark student for follow-up (placeholder)
     */
    protected function markStudentForFollowUp($lecturer, int $studentId, array $data): array
    {
        // Implementation would mark student for follow-up
        return ['message' => 'Student marked for follow-up'];
    }
}
