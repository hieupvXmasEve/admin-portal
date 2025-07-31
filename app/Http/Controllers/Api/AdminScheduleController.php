<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleSessionResource;
use App\Models\ClassSession;
use App\Services\AdminScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class AdminScheduleController extends Controller
{
    public function __construct(
        private readonly AdminScheduleService $adminScheduleService
    ) {}

    /**
     * Get schedule data with filters for grid display
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'semester_id' => 'nullable|integer|exists:semesters,id',
                'lecturer_id' => 'nullable|integer|exists:lectures,id',
                'room_id' => 'nullable|integer|exists:rooms,id',
                'date_range.start' => 'nullable|date',
                'date_range.end' => 'nullable|date|after_or_equal:date_range.start',
            ]);
            $scheduleData = $this->adminScheduleService->getScheduleData($filters);

            return response()->json([
                'success' => true,
                'data' => $scheduleData,
                'filters' => $filters,
                'count' => $scheduleData->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving schedule data', [
                'error' => $e->getMessage(),
                'filters' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schedule data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed information about a specific session
     */
    public function show(ClassSession $session): ScheduleSessionResource
    {
        $session->load([
            'courseOffering.curriculumUnit.unit',
            'courseOffering.semester',
            'lecture',
            'room.campus',
            'attendances',
        ]);

        return new ScheduleSessionResource($session);
    }

    /**
     * Update session details with conflict validation
     */
    public function update(UpdateScheduleRequest $request, ClassSession $session): JsonResponse
    {
        try {
            $updateData = $request->getValidatedSessionData();

            $updatedSession = $this->adminScheduleService->updateSession($session, $updateData);

            return response()->json([
                'success' => true,
                'message' => 'Session updated successfully',
                'data' => new ScheduleSessionResource($updatedSession->load([
                    'courseOffering.curriculumUnit.unit',
                    'courseOffering.semester',
                    'lecture',
                    'room.campus',
                ])),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule conflict detected',
                'errors' => $e->getMessage(),
            ], 409); // Conflict status code
        } catch (\Exception $e) {
            Log::error('Error updating session', [
                'session_id' => $session->id,
                'update_data' => $request->validated(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get filter options for dropdowns
     */
    public function filterOptions(): JsonResponse
    {
        try {
            $options = $this->adminScheduleService->getFilterOptions();

            return response()->json([
                'success' => true,
                'data' => $options,
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving filter options', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve filter options',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
