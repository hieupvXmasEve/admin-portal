<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\CreateSessionRequest;
use App\Http\Requests\Api\V1\Lecturer\ScheduleFilterRequest;
use App\Http\Requests\Api\V1\Lecturer\TimetableFilterRequest;
use App\Http\Requests\Api\V1\Lecturer\UpdateSessionRequest;
use App\Http\Resources\Api\V1\Lecturer\SessionResource;
use App\Http\Resources\Api\V1\Lecturer\TimetableResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClassSession;
use App\Modules\Academic\Delivery\Support\LecturerTimetableService;
use App\Shared\Contracts\Identity\LecturerTeachingActor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    public function __construct(
        protected LecturerTimetableService $timetableService
    ) {}

    /**
     * Get lecturer's timetable
     */
    public function index(TimetableFilterRequest $request): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->validated();

            $timetable = $this->timetableService->getTimetable($lecturer, $filters);

            return ApiResponse::success(
                new TimetableResource($timetable),
                [],
                'Timetable retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve timetable');
        }
    }

    /**
     * Get lecturer's schedule for a date range
     */
    public function schedule(ScheduleFilterRequest $request): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->validated();

            $schedule = $this->timetableService->getSchedule($lecturer, $filters);

            return ApiResponse::success(
                $schedule,
                [],
                'Schedule retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve schedule');
        }
    }

    /**
     * Get upcoming sessions
     */
    public function upcomingSessions(Request $request): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();

        try {
            $days = (int) $request->query('days', 7);
            $limit = (int) $request->query('limit', 20);

            $days = min(max($days, 1), 30); // Between 1 and 30 days
            $limit = min(max($limit, 1), 50); // Between 1 and 50 sessions

            $sessions = $this->timetableService->getUpcomingSessions($lecturer, $days, $limit);

            return ApiResponse::success(
                SessionResource::collection($sessions),
                [],
                'Upcoming sessions retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve upcoming sessions');
        }
    }

    /**
     * Create a new session
     */
    public function createSession(CreateSessionRequest $request): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();

        try {
            $sessionData = $request->validated();

            $result = $this->timetableService->createSession($lecturer, $sessionData);

            return ApiResponse::success(
                $result,
                [],
                'Session created successfully'
            );
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not found or access denied')) {
                return ApiResponse::notFound($e->getMessage());
            }
            if (str_contains($e->getMessage(), 'conflict')) {
                return ApiResponse::validationError(
                    ['scheduling' => [$e->getMessage()]],
                    'Scheduling conflict detected'
                );
            }

            return ApiResponse::serverError('Failed to create session');
        }
    }

    /**
     * Update an existing session
     */
    public function updateSession(UpdateSessionRequest $request, int $sessionId): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();

        try {
            $updateData = $request->validated();

            $result = $this->timetableService->updateSession($lecturer, $sessionId, $updateData);

            return ApiResponse::success(
                $result,
                [],
                'Session updated successfully'
            );
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not found or access denied')) {
                return ApiResponse::notFound($e->getMessage());
            }
            if (str_contains($e->getMessage(), 'conflict')) {
                return ApiResponse::validationError(
                    ['scheduling' => [$e->getMessage()]],
                    'Scheduling conflict detected'
                );
            }
            if (str_contains($e->getMessage(), 'Cannot update')) {
                return ApiResponse::validationError(
                    ['session' => [$e->getMessage()]],
                    'Session cannot be updated'
                );
            }

            return ApiResponse::serverError('Failed to update session');
        }
    }

    /**
     * Cancel a session
     */
    public function cancelSession(Request $request, int $sessionId): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();

        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $reason = $validated['reason'] ?? null;

            $result = $this->timetableService->cancelSession($lecturer, $sessionId, $reason);

            return ApiResponse::success(
                $result,
                [],
                'Session cancelled successfully'
            );
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'not found or access denied')) {
                return ApiResponse::notFound($e->getMessage());
            }
            if (str_contains($e->getMessage(), 'Cannot cancel')) {
                return ApiResponse::validationError(
                    ['session' => [$e->getMessage()]],
                    'Session cannot be cancelled'
                );
            }

            return ApiResponse::serverError('Failed to cancel session');
        }
    }

    /**
     * Get session details
     */
    public function sessionDetails(Request $request, int $sessionId): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();

        try {
            $session = ClassSession::query()
                ->where('lecture_id', $lecturer->lecturerId())
                ->with(['courseOffering.unit', 'room'])
                ->where('id', $sessionId)
                ->first();

            if (! $session) {
                return ApiResponse::notFound('Session not found or access denied');
            }

            return ApiResponse::success(
                new SessionResource($session),
                [],
                'Session details retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve session details');
        }
    }

    /**
     * Get timetable summary
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->only(['start_date', 'end_date']);

            // Default to current week if no dates provided
            if (empty($filters['start_date'])) {
                $filters['start_date'] = now()->startOfWeek()->format('Y-m-d');
            }
            if (empty($filters['end_date'])) {
                $filters['end_date'] = now()->endOfWeek()->format('Y-m-d');
            }

            $timetable = $this->timetableService->getTimetable($lecturer, $filters);

            $summary = [
                'period' => $timetable['period'],
                'summary' => $timetable['summary'],
                'conflicts_count' => count($timetable['conflicts']),
                'has_conflicts' => ! empty($timetable['conflicts']),
                'next_session' => $this->getNextSession($timetable['sessions']),
                'today_sessions' => $this->getTodaySessions($timetable['sessions']),
            ];

            return ApiResponse::success(
                $summary,
                [],
                'Timetable summary retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve timetable summary');
        }
    }

    /**
     * Bulk update sessions
     */
    public function bulkUpdateSessions(Request $request): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();

        try {
            $validated = $request->validate([
                'sessions' => 'required|array|min:1|max:20',
                'sessions.*.session_id' => 'required|integer',
                'sessions.*.updates' => 'required|array',
            ]);

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($validated['sessions'] as $sessionUpdate) {
                try {
                    $result = $this->timetableService->updateSession(
                        $lecturer,
                        $sessionUpdate['session_id'],
                        $sessionUpdate['updates']
                    );

                    $results[] = [
                        'session_id' => $sessionUpdate['session_id'],
                        'status' => 'success',
                        'result' => $result,
                    ];
                    $successCount++;
                } catch (\Exception $e) {
                    $results[] = [
                        'session_id' => $sessionUpdate['session_id'],
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                    $errorCount++;
                }
            }

            return ApiResponse::success([
                'total_processed' => count($validated['sessions']),
                'successful_updates' => $successCount,
                'failed_updates' => $errorCount,
                'results' => $results,
            ], [], 'Bulk session update completed');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to process bulk session updates');
        }
    }

    /**
     * Get next session from timetable data
     */
    protected function getNextSession(array $sessions): ?array
    {
        $now = now();
        $nextSession = null;
        $minDiff = null;

        foreach ($sessions as $dateSessions) {
            foreach ($dateSessions as $session) {
                $sessionDateTime = Carbon::parse($session['date'].' '.$session['start_time']);

                if ($sessionDateTime->gt($now)) {
                    $diff = $sessionDateTime->diffInMinutes($now);

                    if ($minDiff === null || $diff < $minDiff) {
                        $minDiff = $diff;
                        $nextSession = $session;
                    }
                }
            }
        }

        return $nextSession;
    }

    /**
     * Get today's sessions from timetable data
     */
    protected function getTodaySessions(array $sessions): array
    {
        $today = now()->format('Y-m-d');

        return $sessions[$today] ?? [];
    }
}
