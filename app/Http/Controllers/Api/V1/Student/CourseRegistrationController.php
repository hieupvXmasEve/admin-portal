<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Exceptions\BusinessLogicException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\AvailableCoursesRequest;
use App\Http\Requests\Api\V1\Student\CourseRegistrationRequest;
use App\Http\Resources\Api\V1\Student\CourseOfferingResource;
use App\Http\Resources\Api\V1\Student\CourseRegistrationResource;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Services\V1\Student\ConflictDetectionService;
use App\Services\V1\Student\CourseRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CourseRegistrationController extends Controller
{
    public function __construct(
        protected CourseRegistrationService $registrationService,
        protected ConflictDetectionService $conflictDetectionService
    ) {}

    /**
     * Get available courses for registration
     */
    public function availableCourses(AvailableCoursesRequest $request): JsonResponse
    {
        $student = $request->user();

        try {
            $filters = $request->validated();
            $availableCourses = $this->registrationService->getAvailableCourses($student, $filters);
            Log::info('AvailableCourses: '.$availableCourses);

            return ApiResponse::success(
                CourseOfferingResource::collection($availableCourses),
                'Available courses retrieved successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            Log::error('AvailableCourses error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to retrieve available courses');
        }
    }

    /**
     * Register for a course
     */
    public function register(CourseRegistrationRequest $request): JsonResponse
    {
        $student = $request->user();
        try {
            $courseOfferingIds = $request->validated()['course_offering_id'];
            $registrations = [];
            foreach ($courseOfferingIds as $courseOfferingId) {
                $registration = $this->registrationService->registerForCourse($student, $courseOfferingId);
                $registrations[] = $registration;
            }

            // Get formatted registrations for response
            $formattedRegistrations = collect($registrations)->map(function ($registration) {
                // Load the registration with required relationships
                $registration->load([
                    'courseOffering.curriculumUnit.unit',
                    'courseOffering.lecturer',
                    'courseOffering.classSessions.room',
                    'semester',
                ]);

                return $this->registrationService->formatRegistrationForStudent($registration);
            });

            return ApiResponse::success(
                CourseRegistrationResource::collection($formattedRegistrations),
                'Course registration successful',
                201
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to retrieve registrations: '.$e->getMessage());

            return ApiResponse::serverError('Failed to register for course');
        }
    }

    /**
     * Drop a course registration
     */
    public function drop(Request $request, CourseRegistration $registration): JsonResponse
    {
        $student = $request->user();

        try {
            $this->registrationService->dropCourse($student, $registration);

            return ApiResponse::success(
                null,
                'Course dropped successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to drop course');
        }
    }

    /**
     * Get student's current registrations
     */
    public function myRegistrations(Request $request): JsonResponse
    {
        $student = $request->user();

        try {
            $semesterId = $request->query('semester_id');
            $registrations = $this->registrationService->getStudentRegistrations($student, $semesterId);

            return ApiResponse::success(
                CourseRegistrationResource::collection($registrations),
                'Student registrations retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve registrations');
        }
    }

    /**
     * Validate course registration without actually registering
     */
    public function validateRegistration(CourseRegistrationRequest $request): JsonResponse
    {
        $student = $request->user();

        try {
            $courseOfferingIds = $request->validated()['course_offering_id'];
            $validations = [];

            foreach ($courseOfferingIds as $courseOfferingId) {
                $courseOffering = CourseOffering::with([
                    'curriculumUnit.unit',
                    'classSessions',
                    'courseRegistrations',
                ])->findOrFail($courseOfferingId);

                // Use reflection to access the protected method
                $reflection = new \ReflectionClass($this->registrationService);
                $method = $reflection->getMethod('validateRegistration');
                $method->setAccessible(true);

                try {
                    // This will throw BusinessLogicException if validation fails
                    $method->invoke($this->registrationService, $student, $courseOffering);
                    $validations[] = [
                        'course_offering_id' => $courseOfferingId,
                        'can_register' => true,
                        'validation_passed' => true,
                    ];
                } catch (BusinessLogicException $e) {
                    $validations[] = [
                        'course_offering_id' => $courseOfferingId,
                        'can_register' => false,
                        'validation_passed' => false,
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            return ApiResponse::success($validations, 'Registration validation completed');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to validate registration');
        }
    }

    /**
     * Check for schedule conflicts with a specific course
     */
    public function checkScheduleConflicts(Request $request, int $courseOfferingId): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $courseOffering = CourseOffering::with('classSessions')->findOrFail($courseOfferingId);
            $conflicts = $this->conflictDetectionService->detectConflicts($student, $courseOffering);

            return ApiResponse::success([
                'has_conflicts' => ! $conflicts->isEmpty(),
                'conflict_count' => $conflicts->count(),
                'conflicts' => $conflicts->toArray(),
            ], 'Schedule conflicts checked successfully');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to check schedule conflicts');
        }
    }
}
