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
use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Modules\Academic\Delivery\Support\Grading\Presenters\GradeDisplayPresenter;
use App\Services\V1\Student\CourseRegistrationService;
use App\Shared\Contracts\Academic\CourseRosterReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CourseRegistrationController extends Controller
{
    public function __construct(
        protected CourseRegistrationService $registrationService,
        private readonly CourseRosterReader $courseRosters,
    ) {}

    /**
     * Get courses student is currently enrolled in
     */
    public function enrolledCourses(AvailableCoursesRequest $request): JsonResponse
    {
        $student = $request->user();
        Log::info('Getting enrolled courses for student id: '.$student->id);

        try {
            $filters = $request->validated();
            $enrolledCourses = $this->registrationService->getEnrolledCourses($student, $filters);
            Log::info('Enrolled courses count: '.$enrolledCourses->count());

            return ApiResponse::success(
                CourseOfferingResource::collection($enrolledCourses),
                [],
                'Enrolled courses retrieved successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Enrolled courses error: '.$e->getMessage(), [
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
        Log::info('Getting available courses for registration for student id: '.$student->id);

        try {
            $filters = $request->validated();
            $availableCourses = $this->registrationService->getAvailableCoursesForRegistration($student, $filters);
            Log::info('Available courses for registration count: '.$availableCourses->count());

            return ApiResponse::success(
                CourseOfferingResource::collection($availableCourses),
                [],
                'Available courses for registration retrieved successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Available courses for registration error: '.$e->getMessage(), [
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
            if (! $this->courseRosters->studentHasVisibleRegistration($student->id, $courseOfferingId)) {
                return ApiResponse::businessLogicError('You are not enrolled in this course');
            }

            // Get course offering with all necessary relationships
            $courseOffering = CourseOffering::with([
                'unit',
                'lecture',
                'semester',
                'syllabusTemplate',
                'classSessions' => function ($query) {
                    $query->orderBy('session_date')
                        ->orderBy('start_time');
                },
                'classSessions.room',
                'classSessions.lecture',
            ])->findOrFail($courseOfferingId);

            // Get assessment grades for this student and course
            $assessmentScores = AssessmentComponentDetailScore::where('student_id', $student->id)
                ->where('course_offering_id', $courseOfferingId)
                ->with([
                    'assessmentComponentDetail.assessmentComponent',
                ])
                ->get();

            // Get academic record for total grade
            $academicRecord = AcademicRecord::where('student_id', $student->id)
                ->where('course_offering_id', $courseOfferingId)
                ->first();

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
                'grades' => $this->formatGradesTable($assessmentScores, $academicRecord, $courseOffering->syllabusTemplate?->grading_scheme),
            ];

            return ApiResponse::success(
                $courseData,
                [],
                'Course details retrieved successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to retrieve course details: '.$e->getMessage());

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
     * Format grades data as a table structure for API response
     *
     * @param  array<string, mixed>|null  $gradingScheme  the offering's syllabus template scheme, if any
     */
    protected function formatGradesTable($assessmentScores, $academicRecord, ?array $gradingScheme = null): array
    {
        // Group scores by assessment component (groups)
        $gradesByComponent = $assessmentScores->groupBy('assessmentComponentDetail.assessmentComponent.id');

        $assessmentGroups = $gradesByComponent->map(function ($scores, $componentId) {
            $firstScore = $scores->first();
            $component = $firstScore->assessmentComponentDetail->assessmentComponent;

            // Map all detail scores for this component
            $details = $scores->map(function ($score) {
                return [
                    'id' => $score->id,
                    'name' => $score->assessmentComponentDetail->name,
                    'due_date' => $score->assessmentComponentDetail->due_date?->toDateString(),
                    'points_earned' => $score->points_earned,
                    'max_points' => $score->assessmentComponentDetail->max_points,
                    'percentage_score' => $score->percentage_score,
                    'letter_grade' => $score->letter_grade,
                    'status' => $score->status,
                    'graded_at' => $score->graded_at?->toDateString(),
                ];
            })->values()->toArray();

            return [
                'group_id' => $component->id,
                'group_name' => $component->name,
                'group_code' => $component->code,
                'group_type' => $component->type,
                'group_weight' => (float) $component->weight,
                'details' => $details,
            ];
        })->values()->toArray();

        // Scheme grade display only exists for offerings whose syllabus template
        // carries a custom grading scheme (issues 02/03 precedent) — default-weighted
        // offerings must render byte-identical to today, so the key itself is
        // omitted rather than set to null.
        $hasScheme = ! empty($gradingScheme);

        return [
            'assessment_groups' => $assessmentGroups,
            'total_grade' => [
                'final_percentage' => $academicRecord?->final_percentage,
                'final_letter_grade' => $academicRecord?->final_letter_grade,
                'grade_points' => $academicRecord?->grade_points,
                'grade_status' => $academicRecord?->grade_status,
                'completion_status' => $academicRecord?->completion_status,
                ...($hasScheme ? ['grade_display' => $this->schemeGradeDisplay($academicRecord)] : []),
            ],
        ];
    }

    /**
     * Present the stored grade breakdown for a student's course, or null before
     * finalization (no breakdown has been persisted yet). Never recalculates —
     * reads only what finalization/recalculation stored (ADR 0014).
     */
    protected function schemeGradeDisplay(?AcademicRecord $academicRecord): ?array
    {
        if ($academicRecord === null || empty($academicRecord->grade_breakdown)) {
            return null;
        }

        return (new GradeDisplayPresenter)->present($academicRecord);
    }
}
