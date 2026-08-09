<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\EventParticipantResource;
use App\Http\Resources\Api\V1\Student\EventResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Engagement\Actions\EventOperations;
use App\Modules\Engagement\Actions\EventParticipationOperations;
use App\Modules\Engagement\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function __construct(
        protected EventOperations $eventService,
        protected EventParticipationOperations $participationService
    ) {}

    /**
     * List available events for the student's campus with filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $student = $request->user();

            // Get search and filter parameters
            $search = $request->get('search');
            $status = $request->get('status', 'published');
            $timeFilter = $request->get('time_filter'); // upcoming, ongoing, past
            $perPage = min((int) $request->get('per_page', 15), 50);

            // Build filters array
            $filters = [
                'search' => $search,
                'status' => $status,
                'time_filter' => $timeFilter,
            ];

            // Get events for student's campus
            $events = $this->eventService->getEventsForCampus($student->campus_id, $filters);

            // Paginate results manually since we're using Collection
            $currentPage = (int) $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedEvents = $events->slice($offset, $perPage);

            // Load student participation data for each event
            $eventsWithParticipation = $paginatedEvents->map(function ($event) use ($student) {
                $event->student_participation = $event->getStudentParticipation($student->id);

                return $event;
            });

            return ApiResponse::success([
                'events' => EventResource::collection($eventsWithParticipation),
                'pagination' => [
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                    'total' => $events->count(),
                    'last_page' => ceil($events->count() / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $events->count()),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch events for student', [
                'student_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to fetch events', [], 500);
        }
    }

    /**
     * Get detailed information about a specific event
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        try {
            $student = $request->user();

            // Verify event is from student's campus
            if ($event->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Event not found');
            }

            // Only show published events to students
            if ($event->isDraft()) {
                return ApiResponse::notFound('Event not found');
            }

            // Load relationships and student participation
            $event->load(['creator', 'campus']);
            $event->student_participation = $event->getStudentParticipation($student->id);
            $event->statistics = $this->eventService->getEventStatistics($event);

            return ApiResponse::success(
                new EventResource($event)
            );
        } catch (\Exception $e) {
            Log::error('Failed to fetch event details for student', [
                'student_id' => $request->user()?->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch event details', [], 500);
        }
    }

    /**
     * Register student for an event
     */
    public function register(Request $request, Event $event): JsonResponse
    {
        try {
            $student = $request->user();

            // Verify event is from student's campus
            if ($event->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Event not found');
            }

            // Register student for the event
            $participant = $this->participationService->registerStudent($event, (int) $student->id);

            Log::info('Student registered for event via API', [
                'student_id' => $student->id,
                'event_id' => $event->id,
                'participant_id' => $participant->id,
            ]);

            return ApiResponse::success([
                'message' => 'Successfully registered for event',
                'participation' => new EventParticipantResource($participant->load(['event', 'student'])),
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors(), $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 400);
        } catch (\Exception $e) {
            Log::error('Failed to register student for event', [
                'student_id' => $request->user()?->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to register for event', [], 500);
        }
    }

    /**
     * Unregister student from an event
     */
    public function unregister(Request $request, Event $event): JsonResponse
    {
        try {
            $student = $request->user();

            // Verify event is from student's campus
            if ($event->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Event not found');
            }

            // Unregister student from the event
            $success = $this->participationService->unregisterStudent($event, (int) $student->id);

            if (! $success) {
                return ApiResponse::error('Failed to unregister from event', [], 400);
            }

            Log::info('Student unregistered from event via API', [
                'student_id' => $student->id,
                'event_id' => $event->id,
            ]);

            return ApiResponse::success([
                'message' => 'Successfully unregistered from event',
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 400);
        } catch (\Exception $e) {
            Log::error('Failed to unregister student from event', [
                'student_id' => $request->user()?->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to unregister from event', [], 500);
        }
    }

    /**
     * Get student's event participation history
     */
    public function myEvents(Request $request): JsonResponse
    {
        try {
            $student = $request->user();

            // Get filter parameters
            $status = $request->get('status'); // registered, checked_in, completed, cancelled
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $perPage = min((int) $request->get('per_page', 15), 50);

            // Build filters
            $filters = array_filter([
                'status' => $status,
                'campus_id' => $student->campus_id,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);

            // Get student participations
            $participations = $this->participationService->getStudentParticipations((int) $student->id, $filters);

            // Paginate results manually
            $currentPage = (int) $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedParticipations = $participations->slice($offset, $perPage);

            return ApiResponse::success([
                'participations' => EventParticipantResource::collection($paginatedParticipations),
                'pagination' => [
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                    'total' => $participations->count(),
                    'last_page' => ceil($participations->count() / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $participations->count()),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch student event participations', [
                'student_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to fetch event participations', [], 500);
        }
    }

    /**
     * Get detailed information about a specific participation
     */
    public function myEventDetails(Request $request, Event $event): JsonResponse
    {
        try {
            $student = $request->user();

            // Verify event is from student's campus
            if ($event->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Event not found');
            }

            // Get student's participation for this event
            $participation = $event->getStudentParticipation($student->id);

            if (! $participation) {
                return ApiResponse::notFound('No participation found for this event');
            }

            // Load relationships
            $participation->load(['event.creator', 'event.campus', 'checkinStaff']);

            return ApiResponse::success([
                'participation' => new EventParticipantResource($participation),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch student event participation details', [
                'student_id' => $request->user()?->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to fetch participation details', [], 500);
        }
    }
}
