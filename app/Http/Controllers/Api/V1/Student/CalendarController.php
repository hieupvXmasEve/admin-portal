<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\AcademicCalendarResource;
use App\Http\Resources\Api\V1\Student\CurrentSemesterResource;
use App\Http\Resources\Api\V1\Student\SemesterDeadlinesResource;
use App\Http\Resources\Api\V1\Student\SemesterResource;
use App\Http\Responses\ApiResponse;
use App\Models\Semester;
use App\Services\V1\Student\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        protected CalendarService $calendarService
    ) {}

    /**
     * Get all semesters
     */
    public function semesters(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $semesters = $this->calendarService->getSemesters($student);

            return ApiResponse::success(
                new SemesterResource($semesters),
                'Semesters retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve semesters');
        }
    }

    /**
     * Get semester deadlines
     */
    public function semesterDeadlines(Request $request, int $semesterId): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $semester = Semester::findOrFail($semesterId);
            $deadlines = $this->calendarService->getSemesterDeadlines($student, $semester);

            return ApiResponse::success(
                new SemesterDeadlinesResource($deadlines),
                'Semester deadlines retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }
    }

    /**
     * Get academic calendar
     */
    public function academicCalendar(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $calendar = $this->calendarService->getAcademicCalendar($student);

            return ApiResponse::success(
                new AcademicCalendarResource($calendar),
                'Academic calendar retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve academic calendar');
        }
    }

    /**
     * Get current semester information
     */
    public function currentSemester(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $currentSemesterInfo = $this->calendarService->getCurrentSemesterInfo($student);

            return ApiResponse::success(
                new CurrentSemesterResource($currentSemesterInfo),
                'Current semester information retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve current semester information');
        }
    }
}
