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
use App\Services\V1\Student\CourseRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CourseRegistrationController extends Controller
{
    public function __construct(
        protected CourseRegistrationService $registrationService,
    ) {}

    /**
     * Get courses student is currently enrolled in
     */
    public function enrolledCourses(AvailableCoursesRequest $request): JsonResponse
    {
        $student = $request->user();
        Log::info('Getting enrolled courses for student id: ' . $student->id);

        try {
            $filters = $request->validated();
            $enrolledCourses = $this->registrationService->getEnrolledCourses($student, $filters);
            Log::info('Enrolled courses count: ' . $enrolledCourses->count());

            return ApiResponse::success(
                CourseOfferingResource::collection($enrolledCourses),
                [],
                'Enrolled courses retrieved successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Enrolled courses error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to retrieve enrolled courses');
        }
    }

    /**
     * Get courses available for registration (not yet enrolled)
     */
    public function availableForRegistration(AvailableCoursesRequest $request): JsonResponse
    {
        $student = $request->user();
        Log::info('Getting available courses for registration for student id: ' . $student->id);

        try {
            $filters = $request->validated();
            $availableCourses = $this->registrationService->getAvailableCoursesForRegistration($student, $filters);
            Log::info('Available courses for registration count: ' . $availableCourses->count());

            return ApiResponse::success(
                CourseOfferingResource::collection($availableCourses),
                [],
                'Available courses for registration retrieved successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Available courses for registration error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to retrieve available courses for registration');
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
                    'courseOffering.unit',
                    'courseOffering.lecturer',
                    'courseOffering.classSessions.room',
                    'semester',
                ]);

                return $this->registrationService->formatRegistrationForStudent($registration);
            });

            return ApiResponse::success(
                CourseRegistrationResource::collection($formattedRegistrations),
                [],
                'Course registration successful',
                201
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to retrieve registrations: ' . $e->getMessage());

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
                [],
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
                [],
                'Student registrations retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve registrations');
        }
    }
    /**
     * Get course offering detail with full schedule and grades
     */
    public function courseDetail(Request $request, int $courseOfferingId): JsonResponse
    {
        $student = $request->user();

        try {
            // Check if student is registered for this course
            $registration = $student->courseRegistrations()
                ->where('course_offering_id', $courseOfferingId)
                ->whereIn('registration_status', ['registered', 'confirmed'])
                ->first();

            if (!$registration) {
                return ApiResponse::businessLogicError('You are not enrolled in this course');
            }

            // Get course offering with all necessary relationships
            $courseOffering = CourseOffering::with([
                'unit',
                'lecture',
                'semester',
                'classSessions' => function ($query) {
                    $query->orderBy('session_date')
                        ->orderBy('start_time');
                },
                'classSessions.room',
                'classSessions.lecture'
            ])->findOrFail($courseOfferingId);

            // Get assessment grades for this student and course
            $assessmentScores = \App\Models\AssessmentComponentDetailScore::where('student_id', $student->id)
                ->where('course_offering_id', $courseOfferingId)
                ->with([
                    'assessmentComponentDetail.assessmentComponent.assessmentType',
                    'assessmentComponentDetail.assessmentComponent'
                ])
                //                ->orderBy('due_date')
                ->get();

            $courseData = [
                'course_info' => [
                    'id' => $courseOffering->id,
                    'code' => $courseOffering->unit->code,
                    'name' => $courseOffering->unit->name,
                    'credit_points' => (float) $courseOffering->unit->credit_points,
                    'section_code' => $courseOffering->section_code,
                    'semester' => [
                        'id' => $courseOffering->semester->id,
                        'name' => $courseOffering->semester->name,
                        'code' => $courseOffering->semester->code,
                    ],
                    'lecturer' => [
                        'id' => $courseOffering->lecture?->id,
                        'name' => $courseOffering->lecture?->display_name ?? $courseOffering->lecture?->full_name,
                        'email' => $courseOffering->lecture?->email,
                    ],
                ],
                'schedule' => $this->formatSchedule($courseOffering->classSessions),
                'grades' => $this->formatGrades($assessmentScores),
            ];

            return ApiResponse::success(
                $courseData,
                [],
                'Course details retrieved successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to retrieve course details: ' . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve course details');
        }
    }


    /**
     * Format schedule data for API response
     */
    protected function formatSchedule($classSessions): array
    {
        return $classSessions->map(function ($session) {
            return [
                'id' => $session->id,
                'session_title' => $session->session_title,
                'session_date' => $session->session_date?->toDateString(),
                'start_time' => $session->start_time?->format('H:i'),
                'end_time' => $session->end_time?->format('H:i'),
                'duration_minutes' => $session->duration_minutes,
                'session_type' => $session->session_type,
                'delivery_mode' => $session->delivery_mode,
                'status' => $session->status,
                'room' => $session->room ? [
                    'id' => $session->room->id,
                    'code' => $session->room->code,
                    'name' => $session->room->name,
                    'building' => $session->room->building?->name ?? null,
                    'capacity' => $session->room->capacity,
                ] : null,
                'learning_objectives' => $session->learning_objectives,
                'required_materials' => $session->required_materials,
                'topics_covered' => $session->topics_covered,
                'online_meeting_url' => $session->online_meeting_url,
                'student_instructions' => $session->student_instructions,
                'lecturer' => $session->lecture ? [
                    'id' => $session->lecture->id,
                    'name' => $session->lecture->display_name ?? $session->lecture->full_name,
                    'email' => $session->lecture->email,
                ] : null,
            ];
        })->values()->toArray();
    }

    /**
     * Format grades data for API response
     */
    protected function formatGrades($assessmentScores): array
    {
        $gradesByComponent = $assessmentScores->groupBy('assessmentComponentDetail.assessmentComponent.id');

        return $gradesByComponent->map(function ($scores, $componentId) {
            $firstScore = $scores->first();
            $component = $firstScore->assessmentComponentDetail->assessmentComponent;

            return [
                'component_id' => $component->id,
                'component_name' => $component->name,
                'component_code' => $component->code,
                'component_type' => $component->type,
                'component_weight' => $component->weight,
                'due_date' => $component->due_date?->toDateString(),
                'assessments' => $scores->map(function ($score) {
                    return [
                        'id' => $score->id,
                        'name' => $score->assessmentComponentDetail->name,
                        'description' => $score->assessmentComponentDetail->description,
                        'weight' => $score->assessmentComponentDetail->weight,
                        'max_points' => $score->assessmentComponentDetail->max_points,
                        'due_date' => $score->due_date?->toDateString(),
                        'score' => [
                            'points_earned' => $score->points_earned,
                            'percentage_score' => $score->percentage_score,
                            'letter_grade' => $score->letter_grade,
                            'status' => $this->getGradeStatus($score),
                        ],
                        'submission' => [
                            'submitted_at' => $score->submitted_at?->toDateString(),
                            'status' => $score->status,
                            'is_late' => $score->is_late,
                            'late_penalty_applied' => $score->late_penalty_applied,
                        ],
                        'feedback' => [
                            'instructor_feedback' => $score->instructor_feedback,
                            'graded_at' => $score->graded_at?->toDateString(),
                        ],
                    ];
                })->values(),
            ];
        })->values()->toArray();
    }

    /**
     * Get grade status for display
     */
    protected function getGradeStatus($score): string
    {
        if (!$score->points_earned && !$score->percentage_score) {
            return 'not_available';
        }

        return match ($score->score_status) {
            'final' => 'released',
            'provisional' => 'provisional',
            'draft' => 'not_available',
            default => 'not_available',
        };
    }
}
