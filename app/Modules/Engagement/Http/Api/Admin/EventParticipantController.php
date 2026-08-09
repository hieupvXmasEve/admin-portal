<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventParticipantResource;
use App\Http\Responses\ApiResponse;
use App\Models\Program;
use App\Models\Specialization;
use App\Modules\Engagement\Actions\EventParticipationOperations;
use App\Modules\Engagement\Http\Requests\AddManualEventParticipantsRequest;
use App\Modules\Engagement\Http\Requests\BulkUpdateEventParticipantsRequest;
use App\Modules\Engagement\Http\Requests\ListEventParticipantsRequest;
use App\Modules\Engagement\Http\Requests\ManualEventParticipantSearchRequest;
use App\Modules\Engagement\Http\Requests\RemoveManualEventParticipantsRequest;
use App\Modules\Engagement\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventParticipantController extends Controller
{
    public function __construct(
        private EventParticipationOperations $participationService
    ) {}

    /**
     * Get filter options for student selection
     */
    public function getFilterOptions(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        // Get programs available in this campus
        $programs = Program::whereHas('students', function ($query) use ($event) {
            $query->where('campus_id', $event->campus_id)
                ->where('status', 'active');
        })->select('id', 'name', 'code')->get();

        // Get specializations available in this campus
        $specializations = Specialization::whereHas('students', function ($query) use ($event) {
            $query->where('campus_id', $event->campus_id)
                ->where('status', 'active');
        })->select('id', 'name', 'code')->get();

        // Academic status options
        $academicStatuses = [
            ['value' => 'good_standing', 'label' => 'Good Standing'],
            ['value' => 'probation', 'label' => 'Academic Probation'],
            ['value' => 'warning', 'label' => 'Academic Warning'],
            ['value' => 'suspension', 'label' => 'Academic Suspension'],
        ];

        return ApiResponse::success([
            'programs' => $programs,
            'specializations' => $specializations,
            'academic_statuses' => $academicStatuses,
        ]);
    }

    /**
     * Search for students by student IDs for manual event participation
     */
    public function searchStudents(ManualEventParticipantSearchRequest $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        // if (!$event->isManual()) {
        //     abort(403, 'Student selection is only available for manual events');
        // }

        $students = $this->participationService->searchStudentsForManualEvent(
            $event,
            $request->validated('student_ids')
        );

        return ApiResponse::success(['students' => $students]);
    }

    /**
     * Add students to manual event
     */
    public function addParticipants(AddManualEventParticipantsRequest $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        // if (!$event->isManual()) {
        //     abort(403, 'Can only add participants to manual events');
        // }

        try {
            $validated = $request->validated();
            $results = $this->participationService->addManualParticipants(
                $event,
                $validated['student_ids'],
                $validated['status'],
                $request->user(),
                $validated['bonus_gold_amount'] ?? null,
                $validated['description'] ?? null,
            );

            return ApiResponse::success($results, message: 'Participants processed successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to add participants: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * Get event participants with filtering
     */
    public function getParticipants(ListEventParticipantsRequest $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        $validated = $request->validated();
        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'search' => $validated['search'] ?? null,
            'gold_awarded' => $validated['gold_awarded'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
        $perPage = $validated['per_page'] ?? 15;

        $participants = $this->participationService->getEventParticipants(
            $event,
            $filters,
            $perPage
        );

        return ApiResponse::success(
            EventParticipantResource::collection($participants->items()),
            [
                'current_page' => $participants->currentPage(),
                'last_page' => $participants->lastPage(),
                'per_page' => $participants->perPage(),
                'total' => $participants->total(),
                'from' => $participants->firstItem(),
                'to' => $participants->lastItem(),
            ],
        );
    }

    /**
     * Bulk update participant status
     */
    public function bulkUpdateStatus(BulkUpdateEventParticipantsRequest $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        if (! $event->isManual()) {
            abort(403, 'Can only bulk update participants for manual events');
        }

        try {
            $validated = $request->validated();
            $results = $this->participationService->bulkUpdateParticipantStatus(
                $event,
                $validated['participant_ids'],
                $validated['status'],
                $request->user(),
            );

            return ApiResponse::success($results, message: 'Participant statuses updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update participant statuses: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * Remove participants from manual event
     */
    public function removeParticipants(RemoveManualEventParticipantsRequest $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        if (! $event->isManual()) {
            abort(403, 'Can only remove participants from manual events');
        }

        try {
            $validated = $request->validated();
            $results = $this->participationService->removeManualParticipants(
                $event,
                $validated['participant_ids'],
                $request->user(),
            );

            return ApiResponse::success($results, message: 'Participants removed successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to remove participants: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * Get manual event statistics
     */
    public function getStatistics(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (! $campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        // if (!$event->isManual()) {
        //     abort(403, 'Statistics are only available for manual events');
        // }

        $statistics = $this->participationService->getManualEventStatistics($event);

        return ApiResponse::success($statistics);
    }
}
