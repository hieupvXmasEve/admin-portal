<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

class RegistrationController extends Controller
{
    public function __construct(
        private RegistrationService $registrationService
    ) {}

    /**
     * Get all registrations for the authenticated student
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $validator = Validator::make($request->all(), [
            'semester_id' => 'nullable|exists:semesters,id',
            'status' => 'nullable|in:registered,confirmed,dropped,withdrawn,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = CourseRegistration::with([
            'courseOffering.unit',
            'courseOffering.instructor',
            'semester'
        ])->where('student_id', $student->id);

        if ($request->semester_id) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->status) {
            $query->where('registration_status', $request->status);
        }

        $registrations = $query->orderBy('registration_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'registrations' => $registrations->map(function ($registration) {
                    return [
                        'id' => $registration->id,
                        'course_offering' => [
                            'id' => $registration->courseOffering->id,
                            'course_code' => $registration->courseOffering->course_code,
                            'section_code' => $registration->courseOffering->section_code,
                            'course_title' => $registration->courseOffering->course_title,
                            'credit_hours' => $registration->courseOffering->credit_hours,
                            'delivery_mode' => $registration->courseOffering->delivery_mode,
                            'schedule' => $registration->courseOffering->schedule,
                            'location' => $registration->courseOffering->location,
                            'instructor' => $registration->courseOffering->instructor ? [
                                'name' => $registration->courseOffering->instructor->name,
                                'email' => $registration->courseOffering->instructor->email,
                            ] : null,
                        ],
                        'semester' => [
                            'id' => $registration->semester->id,
                            'name' => $registration->semester->name,
                            'code' => $registration->semester->code,
                        ],
                        'registration_status' => $registration->registration_status,
                        'registration_date' => $registration->registration_date->format('Y-m-d H:i:s'),
                        'registration_method' => $registration->registration_method,
                        'credit_hours' => $registration->credit_hours,
                        'final_grade' => $registration->final_grade,
                        'grade_points' => $registration->grade_points,
                        'tuition_amount' => $registration->tuition_amount,
                        'fees_amount' => $registration->fees_amount,
                        'payment_status' => $registration->payment_status,
                        'can_drop' => $registration->canDrop(),
                        'can_withdraw' => $registration->canWithdraw(),
                    ];
                })
            ]
        ]);
    }

    /**
     * Get current semester registrations
     */
    public function current(Request $request): JsonResponse
    {
        $student = $request->user();

        // Get current active semester
        $currentSemester = Semester::where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (!$currentSemester) {
            return response()->json([
                'success' => false,
                'message' => 'No active semester found'
            ], 404);
        }

        $registrations = $this->registrationService->getStudentRegistrations(
            $student,
            $currentSemester->id
        );

        return response()->json([
            'success' => true,
            'data' => [
                'semester' => [
                    'id' => $currentSemester->id,
                    'name' => $currentSemester->name,
                    'code' => $currentSemester->code,
                    'start_date' => $currentSemester->start_date?->format('Y-m-d'),
                    'end_date' => $currentSemester->end_date?->format('Y-m-d'),
                ],
                'registrations' => $registrations,
                'summary' => [
                    'total_courses' => count($registrations),
                    'total_credits' => array_sum(array_column($registrations, 'credit_hours')),
                    'active_registrations' => count(array_filter($registrations, function ($reg) {
                        return in_array($reg['registration_status'], ['registered', 'confirmed']);
                    })),
                ]
            ]
        ]);
    }

    /**
     * Register for a course
     */
    public function register(Request $request): JsonResponse
    {
        $student = $request->user();

        $validator = Validator::make($request->all(), [
            'course_offering_id' => 'required|exists:course_offerings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $registration = $this->registrationService->registerForCourse(
                $student,
                $request->course_offering_id,
                'online'
            );

            return response()->json([
                'success' => true,
                'message' => 'Successfully registered for course',
                'data' => [
                    'registration' => [
                        'id' => $registration->id,
                        'course_code' => $registration->courseOffering->course_code,
                        'course_title' => $registration->courseOffering->course_title,
                        'credit_hours' => $registration->credit_hours,
                        'registration_date' => $registration->registration_date->format('Y-m-d H:i:s'),
                        'tuition_amount' => $registration->tuition_amount,
                    ]
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Drop a course
     */
    public function drop(CourseRegistration $registration, Request $request): JsonResponse
    {
        $student = $request->user();

        // Verify the registration belongs to the authenticated student
        if ($registration->student_id !== $student->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $this->registrationService->dropCourse($registration);

            return response()->json([
                'success' => true,
                'message' => 'Course dropped successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Withdraw from a course
     */
    public function withdraw(CourseRegistration $registration, Request $request): JsonResponse
    {
        $student = $request->user();

        // Verify the registration belongs to the authenticated student
        if ($registration->student_id !== $student->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $this->registrationService->withdrawFromCourse($registration);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawn from course successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Check eligibility for a course
     */
    public function checkEligibility(CourseOffering $courseOffering, Request $request): JsonResponse
    {
        $student = $request->user();

        try {
            // Get available courses which includes eligibility check
            $availableCourses = $this->registrationService->getAvailableCoursesForStudent(
                $student,
                $courseOffering->semester_id
            );

            $courseInfo = collect($availableCourses)->firstWhere('offering.id', $courseOffering->id);

            if (!$courseInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found or not available'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'eligible' => $courseInfo['eligible'],
                    'reasons' => $courseInfo['reasons'],
                    'available_spots' => $courseInfo['available_spots'],
                    'total_cost' => $courseInfo['total_cost'],
                    'course_info' => [
                        'id' => $courseOffering->id,
                        'course_code' => $courseOffering->course_code,
                        'course_title' => $courseOffering->course_title,
                        'credit_hours' => $courseOffering->credit_hours,
                        'max_enrollment' => $courseOffering->max_enrollment,
                        'current_enrollment' => $courseOffering->current_enrollment,
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Check schedule conflicts
     */
    public function checkScheduleConflicts(CourseOffering $courseOffering, Request $request): JsonResponse
    {
        $student = $request->user();

        // Get student's current registrations for the semester
        $currentRegistrations = CourseRegistration::with('courseOffering')
            ->where('student_id', $student->id)
            ->where('semester_id', $courseOffering->semester_id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->get();

        $conflicts = [];

        foreach ($currentRegistrations as $registration) {
            // TODO: Implement actual schedule conflict checking
            // This would compare time slots, days of week, etc.
            // For now, we'll return a simple structure
            
            if ($this->hasTimeConflict($courseOffering->schedule, $registration->courseOffering->schedule)) {
                $conflicts[] = [
                    'course_code' => $registration->courseOffering->course_code,
                    'course_title' => $registration->courseOffering->course_title,
                    'schedule' => $registration->courseOffering->schedule,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_conflicts' => !empty($conflicts),
                'conflicts' => $conflicts,
                'course_schedule' => $courseOffering->schedule,
            ]
        ]);
    }

    /**
     * Simple time conflict check (placeholder implementation)
     */
    private function hasTimeConflict(array $schedule1 = null, array $schedule2 = null): bool
    {
        // TODO: Implement actual schedule conflict logic
        // This would compare days of week, start/end times, etc.
        return false;
    }
}
