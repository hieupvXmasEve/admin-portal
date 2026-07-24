<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\CourseFilterRequest;
use App\Http\Requests\Api\V1\Lecturer\StudentFilterRequest;
use App\Http\Resources\Api\V1\Lecturer\CourseDetailResource;
use App\Http\Resources\Api\V1\Lecturer\CourseOfferingResource;
use App\Http\Resources\Api\V1\Lecturer\CourseStudentResource;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Modules\Academic\Delivery\Queries\GetLecturerCourseSessionAttendanceQuery;
use App\Services\V1\Lecturer\LecturerCourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(
        protected LecturerCourseService $courseService,
        private readonly GetLecturerCourseSessionAttendanceQuery $sessionAttendance,
    ) {}

    /**
     * Get lecturer's course offerings with filtering and pagination
     */
    public function index(CourseFilterRequest $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->validated();
            $perPage = (int) $request->query('per_page', 15);
            $perPage = min(max($perPage, 5), 50); // Ensure between 5 and 50

            $courseOfferings = $this->courseService->getCourseOfferings($lecturer, $filters, $perPage);

            return ApiResponse::paginated(
                $courseOfferings->through(fn ($offering) => new CourseOfferingResource($offering)),
                'Course offerings retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve course offerings');
        }
    }

    /**
     * Get detailed course offering information
     */
    public function show(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $courseDetails = $this->courseService->getCourseOfferingDetails($lecturer, $courseOffering->id);

            if (! $courseDetails) {
                return ApiResponse::success([]);
            }

            return ApiResponse::success(
                new CourseDetailResource($courseDetails),
                [],
                'Course details retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve course details');
        }
    }

    /**
     * Get unit information by course offering id
     */
    public function unit(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $unit = $this->courseService->getUnitByCourseOfferingId($lecturer, $courseOffering->id);

            return ApiResponse::success(
                $unit,
                [],
                'Unit information retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve unit information');
        }
    }

    /**
     * Get course statistics
     */
    public function statistics(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $statistics = $this->courseService->getCourseStatistics($lecturer, $courseOffering->id);

            return ApiResponse::success(
                $statistics,
                [],
                'Course statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Course offering not found or access denied') {
                return ApiResponse::notFound($e->getMessage());
            }

            return ApiResponse::serverError('Failed to retrieve course statistics');
        }
    }

    /**
     * Get enrolled students for a course
     */
    public function students(StudentFilterRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->validated();
            $students = $this->courseService->getCourseStudents($lecturer, $courseOffering->id, $filters);

            return ApiResponse::success(
                CourseStudentResource::collection($students),
                [],
                'Course students retrieved successfully'
            );
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Course offering not found or access denied') {
                return ApiResponse::notFound($e->getMessage());
            }

            return ApiResponse::serverError('Failed to retrieve course students');
        }
    }

    /**
     * Get course sessions
     */
    public function sessions(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $sessions = $this->sessionAttendance->handle($lecturer->id, $courseOffering->id);

            return ApiResponse::success(
                $sessions,
                [],
                'Course sessions retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve course sessions');
        }
    }

    /**
     * Get filter options for courses
     */
    public function filterOptions(Request $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filterOptions = $this->courseService->getFilterOptions($lecturer);

            return ApiResponse::success(
                $filterOptions,
                [],
                'Filter options retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve filter options');
        }
    }

    /**
     * Get course offering summary for dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $semesterId = $request->query('semester_id');
            $filters = $semesterId ? ['semester_id' => $semesterId] : [];

            // Get first page of courses for summary
            $courseOfferings = $this->courseService->getCourseOfferings($lecturer, $filters, 100);
            $offerings = $courseOfferings->getCollection();
            $totalStudents = $offerings->sum(fn ($offering) => $offering->activeClassRosterEnrollmentCount());
            $totalCapacity = $offerings->sum('max_capacity');

            $summary = [
                'total_courses' => $courseOfferings->total(),
                'active_courses' => $offerings->count(),
                'total_students' => $totalStudents,
                'average_enrollment' => $offerings->count() > 0
                    ? round($totalStudents / $offerings->count(), 1)
                    : 0,
                'delivery_mode_breakdown' => $offerings->groupBy('delivery_mode')
                    ->map(fn ($group) => $group->count()),
                'capacity_utilization' => $totalCapacity > 0
                    ? round(($totalStudents / $totalCapacity) * 100, 1)
                    : 0,
            ];

            return ApiResponse::success(
                $summary,
                [],
                'Course summary retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve course summary');
        }
    }
}
