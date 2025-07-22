<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\TimetableFilterRequest;
use App\Http\Resources\Api\V1\Student\TimetableResource;
use App\Http\Resources\Api\V1\Student\ClassSessionDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClassSession;
use App\Services\V1\Student\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    public function __construct(
        protected TimetableService $timetableService
    ) {}

    /**
     * Get student's complete timetable
     */
    public function index(TimetableFilterRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();
        
        try {
            $filters = $request->validated();
            $semesterId = $filters['semester_id'] ?? null;
            unset($filters['semester_id']);
            
            $timetable = $this->timetableService->getStudentTimetable($student, $semesterId, $filters);
            
            return ApiResponse::success(
                new TimetableResource($timetable),
                'Timetable retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve timetable');
        }
    }

    /**
     * Get weekly timetable view
     */
    public function weekly(TimetableFilterRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();
        
        try {
            $filters = $request->validated();
            $semesterId = $filters['semester_id'] ?? null;
            unset($filters['semester_id']);
            
            $weeklyTimetable = $this->timetableService->getWeeklyTimetable($student, $semesterId, $filters);
            
            return ApiResponse::success(
                $weeklyTimetable,
                'Weekly timetable retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve weekly timetable');
        }
    }

    /**
     * Get class session detail
     */
    public function classSessionDetail(Request $request, ClassSession $classSession): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();
        
        try {
            $sessionDetail = $this->timetableService->getClassSessionDetail($classSession, $student);
            
            return ApiResponse::success(
                new ClassSessionDetailResource($sessionDetail),
                'Class session detail retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }
    }

    /**
     * Get available filter options
     */
    public function filterOptions(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();
        
        try {
            $semesterId = $request->query('semester_id');
            $filterOptions = $this->timetableService->getFilterOptions($student, $semesterId);
            
            return ApiResponse::success(
                $filterOptions,
                'Filter options retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve filter options');
        }
    }
}
