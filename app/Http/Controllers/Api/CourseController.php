<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{
    public function __construct(
        private RegistrationService $registrationService
    ) {}

    /**
     * Get available courses for registration
     */
    public function available(Request $request): JsonResponse
    {
        $student = $request->user();

        $validator = Validator::make($request->all(), [
            'semester_id' => 'nullable|exists:semesters,id',
            'delivery_mode' => 'nullable|in:in_person,online,hybrid',
            'search' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Use current semester if not specified
        $semesterId = $request->semester_id;
        if (! $semesterId) {
            $currentSemester = Semester::where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            if (! $currentSemester) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active semester found',
                ], 404);
            }

            $semesterId = $currentSemester->id;
        }

        try {
            $availableCourses = $this->registrationService->getAvailableCoursesForStudent(
                $student,
                $semesterId
            );

            // Apply filters
            if ($request->delivery_mode) {
                $availableCourses = array_filter($availableCourses, function ($course) use ($request) {
                    return $course['offering']['delivery_mode'] === $request->delivery_mode;
                });
            }

            if ($request->search) {
                $search = strtolower($request->search);
                $availableCourses = array_filter($availableCourses, function ($course) use ($search) {
                    return str_contains(strtolower($course['offering']['course_code']), $search) ||
                        str_contains(strtolower($course['offering']['course_title']), $search);
                });
            }

            // Format response
            $formattedCourses = array_map(function ($course) {
                return [
                    'id' => $course['offering']['id'],
                    'course_code' => $course['offering']['course_code'],
                    'section_code' => $course['offering']['section_code'],
                    'course_title' => $course['offering']['course_title'],
                    'credit_hours' => $course['offering']['credit_hours'],
                    'delivery_mode' => $course['offering']['delivery_mode'],
                    'schedule' => $course['offering']['schedule'],
                    'location' => $course['offering']['location'],
                    'max_enrollment' => $course['offering']['max_enrollment'],
                    'current_enrollment' => $course['offering']['current_enrollment'],
                    'available_spots' => $course['available_spots'],
                    'instructor' => $course['offering']['lecture'] ? [
                        'name' => $course['offering']['lecture']['display_name'],
                        'email' => $course['offering']['lecture']['email'],
                    ] : null,
                    'unit' => $course['offering']['unit'] ? [
                        'code' => $course['offering']['unit']['code'],
                        'name' => $course['offering']['unit']['name'],
                        'description' => $course['offering']['unit']['description'],
                    ] : null,
                    'tuition_per_credit' => $course['offering']['tuition_per_credit'],
                    'additional_fees' => $course['offering']['additional_fees'],
                    'total_cost' => $course['total_cost'],
                    'prerequisites' => $course['offering']['prerequisites'],
                    'eligible' => $course['eligible'],
                    'eligibility_reasons' => $course['reasons'],
                    'registration_dates' => [
                        'start' => $course['offering']['registration_start_date'],
                        'end' => $course['offering']['registration_end_date'],
                    ],
                    'important_dates' => [
                        'drop_deadline' => $course['offering']['drop_deadline'],
                        'withdrawal_deadline' => $course['offering']['withdrawal_deadline'],
                    ],
                ];
            }, $availableCourses);

            return response()->json([
                'success' => true,
                'data' => [
                    'courses' => array_values($formattedCourses),
                    'total_count' => count($formattedCourses),
                    'semester_id' => $semesterId,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get course offering details
     */
    public function show(CourseOffering $courseOffering): JsonResponse
    {
        $courseOffering->load(['unit', 'lecture', 'semester']);

        return response()->json([
            'success' => true,
            'data' => [
                'course_offering' => [
                    'id' => $courseOffering->id,
                    'course_code' => $courseOffering->course_code,
                    'section_code' => $courseOffering->section_code,
                    'course_title' => $courseOffering->course_title,
                    'credit_hours' => $courseOffering->credit_hours,
                    'delivery_mode' => $courseOffering->delivery_mode,
                    'schedule' => $courseOffering->schedule,
                    'location' => $courseOffering->location,
                    'max_enrollment' => $courseOffering->max_enrollment,
                    'current_enrollment' => $courseOffering->current_enrollment,
                    'waitlist_capacity' => $courseOffering->waitlist_capacity,
                    'current_waitlist' => $courseOffering->current_waitlist,
                    'available_spots' => $courseOffering->getAvailableSpots(),
                    'status' => $courseOffering->status,
                    'tuition_per_credit' => $courseOffering->tuition_per_credit,
                    'additional_fees' => $courseOffering->additional_fees,
                    'prerequisites' => $courseOffering->prerequisites,
                    'notes' => $courseOffering->notes,
                    'registration_dates' => [
                        'start' => $courseOffering->registration_start_date?->format('Y-m-d'),
                        'end' => $courseOffering->registration_end_date?->format('Y-m-d'),
                    ],
                    'important_dates' => [
                        'drop_deadline' => $courseOffering->drop_deadline?->format('Y-m-d'),
                        'withdrawal_deadline' => $courseOffering->withdrawal_deadline?->format('Y-m-d'),
                    ],
                    'unit' => $courseOffering->unit ? [
                        'id' => $courseOffering->unit->id,
                        'code' => $courseOffering->unit->code,
                        'name' => $courseOffering->unit->name,
                        'description' => $courseOffering->unit->description,
                        'credit_points' => $courseOffering->unit->credit_points,
                    ] : null,
                    'instructor' => $courseOffering->lecture ? [
                        'id' => $courseOffering->lecture->id,
                        'name' => $courseOffering->lecture->display_name,
                        'email' => $courseOffering->lecture->email,
                    ] : null,
                    'semester' => [
                        'id' => $courseOffering->semester->id,
                        'name' => $courseOffering->semester->name,
                        'code' => $courseOffering->semester->code,
                        'start_date' => $courseOffering->semester->start_date?->format('Y-m-d'),
                        'end_date' => $courseOffering->semester->end_date?->format('Y-m-d'),
                    ],
                    'campus' => [
                        'id' => $courseOffering->campus->id,
                        'name' => $courseOffering->campus->name,
                        'code' => $courseOffering->campus->code,
                        'address' => $courseOffering->campus->address,
                    ],
                    'is_available_for_registration' => $courseOffering->isAvailableForRegistration(),
                    'is_registration_period_open' => $courseOffering->isRegistrationPeriodOpen(),
                    'is_full' => $courseOffering->isFull(),
                    'has_waitlist_space' => $courseOffering->hasWaitlistSpace(),
                ],
            ],
        ]);
    }

    /**
     * Get course prerequisites
     */
    public function prerequisites(CourseOffering $courseOffering): JsonResponse
    {
        $prerequisites = $courseOffering->prerequisites ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'course_code' => $courseOffering->course_code,
                'course_title' => $courseOffering->course_title,
                'prerequisites' => $prerequisites,
                'has_prerequisites' => ! empty($prerequisites),
            ],
        ]);
    }

    /**
     * Get available semesters
     */
    public function semesters(Request $request): JsonResponse
    {
        $semesters = Semester::where('is_active', true)
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($semester) {
                return [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                    'enrollment_start_date' => $semester->enrollment_start_date?->format('Y-m-d'),
                    'enrollment_end_date' => $semester->enrollment_end_date?->format('Y-m-d'),
                    'is_current' => $semester->start_date <= now() && $semester->end_date >= now(),
                    'is_enrollment_open' => $semester->enrollment_start_date <= now() &&
                        $semester->enrollment_end_date >= now(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'semesters' => $semesters,
                'total_count' => $semesters->count(),
            ],
        ]);
    }

    /**
     * Get courses for a specific semester
     */
    public function semesterCourses(Semester $semester, Request $request): JsonResponse
    {
        $student = $request->user();

        $validator = Validator::make($request->all(), [
            'delivery_mode' => 'nullable|in:in_person,online,hybrid',
            'search' => 'nullable|string|max:255',
            'available_only' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = CourseOffering::with(['curriculumUnit.unit', 'lecture'])
            ->forSemester($semester->id)
            ->forCampus($student->campus_id);

        if ($request->available_only) {
            $query->availableForRegistration();
        }

        if ($request->delivery_mode) {
            $query->byDeliveryMode($request->delivery_mode);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('course_code', 'like', "%{$search}%")
                    ->orWhere('course_title', 'like', "%{$search}%");
            });
        }

        $courses = $query->orderBy('course_code')->get();

        $formattedCourses = $courses->map(function ($course) {
            return [
                'id' => $course->id,
                'course_code' => $course->course_code,
                'section_code' => $course->section_code,
                'course_title' => $course->course_title,
                'credit_hours' => $course->credit_hours,
                'delivery_mode' => $course->delivery_mode,
                'schedule' => $course->schedule,
                'location' => $course->location,
                'max_enrollment' => $course->max_enrollment,
                'current_enrollment' => $course->current_enrollment,
                'available_spots' => $course->getAvailableSpots(),
                'status' => $course->status,
//                'total_cost' => $course->getTotalTuition(),
                'instructor' => $course->lecture ? [
                    'name' => $course->lecture->display_name,
                    'email' => $course->lecture->email,
                ] : null,
                'unit' => $course->unit ? [
                    'code' => $course->unit->code,
                    'name' => $course->unit->name,
                ] : null,
                'is_available_for_registration' => $course->isAvailableForRegistration(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                ],
                'courses' => $formattedCourses,
                'total_count' => $formattedCourses->count(),
                'filters_applied' => [
                    'delivery_mode' => $request->delivery_mode,
                    'search' => $request->search,
                    'available_only' => $request->boolean('available_only'),
                ],
            ],
        ]);
    }
}
