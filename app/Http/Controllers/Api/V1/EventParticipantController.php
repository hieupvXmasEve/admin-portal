<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\StudentResource;
use App\Http\Resources\EventParticipantResource;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Student;
use App\Models\Program;
use App\Models\Specialization;
use App\Services\EventParticipationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EventParticipantController extends Controller
{
    public function __construct(
        private EventParticipationService $participationService
    ) {}

    /**
     * Get filter options for student selection
     */
    public function getFilterOptions(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (!$campus || (int) $event->campus_id !== (int) $campus) {
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

        return response()->json([
            'success' => true,
            'data' => [
                'programs' => $programs,
                'specializations' => $specializations,
                'academic_statuses' => $academicStatuses,
            ]
        ]);
    }

    /**
     * Search for students by student IDs for manual event participation
     */
    public function searchStudents(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (!$campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        // if (!$event->isManual()) {
        //     abort(403, 'Student selection is only available for manual events');
        // }

        $validated = $request->validate([
            'student_ids' => ['required', 'string'],
        ]);

        $students = $this->participationService->searchStudentsForManualEvent(
            $event,
            $validated['student_ids']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'students' => $students,
            ],
        ]);
    }

    /**
     * Add students to manual event
     */
    public function addParticipants(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (!$campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        // if (!$event->isManual()) {
        //     abort(403, 'Can only add participants to manual events');
        // }

        $request->validate([
            'student_ids' => 'required|array|min:1|max:100',
            'student_ids.*' => 'required|integer|exists:students,id',
            'status' => 'required|in:registered,completed',
            'bonus_gold_amount' => 'nullable|numeric|min:0|max:999999.99',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $results = $this->participationService->addManualParticipants(
                $event,
                $request->student_ids,
                $request->status,
                $request->user(),
                $request->bonus_gold_amount,
                $request->description
            );

            return response()->json([
                'success' => true,
                'message' => 'Participants processed successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add participants: ' . $e->getMessage(),
                'errors' => ['general' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Get event participants with filtering
     */
    public function getParticipants(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (!$campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        $filters = $request->only(['status', 'search', 'gold_awarded']);
        $perPage = min($request->get('per_page', 15), 50);

        $participants = $this->participationService->getEventParticipants(
            $event,
            $filters,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => EventParticipantResource::collection($participants->items()),
            'meta' => [
                'current_page' => $participants->currentPage(),
                'last_page' => $participants->lastPage(),
                'per_page' => $participants->perPage(),
                'total' => $participants->total(),
                'from' => $participants->firstItem(),
                'to' => $participants->lastItem(),
            ]
        ]);
    }

    /**
     * Bulk update participant status
     */
    public function bulkUpdateStatus(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (!$campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        if (!$event->isManual()) {
            abort(403, 'Can only bulk update participants for manual events');
        }

        $request->validate([
            'participant_ids' => 'required|array|min:1|max:50',
            'participant_ids.*' => 'required|integer|exists:event_participants,id',
            'status' => 'required|in:registered,completed,cancelled',
        ]);

        try {
            $results = $this->participationService->bulkUpdateParticipantStatus(
                $event,
                $request->participant_ids,
                $request->status,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Participant statuses updated successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update participant statuses: ' . $e->getMessage(),
                'errors' => ['general' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Remove participants from manual event
     */
    public function removeParticipants(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (!$campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        if (!$event->isManual()) {
            abort(403, 'Can only remove participants from manual events');
        }

        $request->validate([
            'participant_ids' => 'required|array|min:1|max:50',
            'participant_ids.*' => 'required|integer|exists:event_participants,id',
        ]);

        try {
            $results = $this->participationService->removeManualParticipants(
                $event,
                $request->participant_ids,
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Participants removed successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove participants: ' . $e->getMessage(),
                'errors' => ['general' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * Get manual event statistics
     */
    public function getStatistics(Request $request, Event $event): JsonResponse
    {
        $campus = session('current_campus_id');
        if (!$campus || (int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        // if (!$event->isManual()) {
        //     abort(403, 'Statistics are only available for manual events');
        // }

        $statistics = $this->participationService->getManualEventStatistics($event);

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }
}
