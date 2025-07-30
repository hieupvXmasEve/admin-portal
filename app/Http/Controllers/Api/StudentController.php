<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\Student\StudentResource;
use App\Models\Student;
use App\Models\CourseRegistration;
use App\Models\GraduationRequirement;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;


class StudentController extends Controller
{
    public function __construct(protected StudentService $studentService) {}
    /**
     * Display a listing of students for current campus
     */
    public function index(Request $request): JsonResponse
    {
        $campusId = session()->get('current_campus_id');

        if (!$campusId) {
            return response()->json([
                'success' => false,
                'message' => 'No campus selected',
            ], 400);
        }

        $filters = $request->only(['search', 'status', 'program_id']);
        $students = $this->studentService->getStudentsByCampus($campusId, $filters);

        return response()->json([
            'success' => true,
            'data' => [
                'students' => StudentResource::collection($students->items()),
                'pagination' => [
                    'current_page' => $students->currentPage(),
                    'last_page' => $students->lastPage(),
                    'per_page' => $students->perPage(),
                    'total' => $students->total(),
                ],
            ],
        ]);
    }

    /**
     * Store a newly created and admitted student
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        try {
            $student = $this->studentService->createAdmittedStudent($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Student created and admitted successfully',
                'data' => [
                    'student' => new StudentResource($student),
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get students count by status for current campus
     */
    public function stats(): JsonResponse
    {
        $campusId = session()->get('current_campus_id');

        if (!$campusId) {
            return response()->json([
                'success' => false,
                'message' => 'No campus selected',
            ], 400);
        }

        $stats = Student::where('campus_id', $campusId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => array_sum($stats),
                'by_status' => $stats,
            ],
        ]);
    }

    // Admission is now part of student creation

    /**
     * Get student profile
     */
    public function profile(Request $request): JsonResponse
    {
        $student = $request->user()->load([
            'campus',
            'program',
            'specialization',
            'curriculumVersion'
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
                    'gender' => $student->gender,
                    'nationality' => $student->nationality,
                    'address' => $student->address,
                    'avatar_url' => $student->avatar_url,
                    'status' => $student->status,
                    'admission_date' => $student->admission_date?->format('Y-m-d'),
                    'expected_graduation_date' => $student->expected_graduation_date?->format('Y-m-d'),
                    'campus' => $student->campus ? [
                        'id' => $student->campus->id,
                        'name' => $student->campus->name,
                        'code' => $student->campus->code,
                        'address' => $student->campus->address,
                    ] : null,
                    'program' => $student->program ? [
                        'id' => $student->program->id,
                        'name' => $student->program->name,
                        'code' => $student->program->code ?? null,
                        'description' => $student->program->description ?? null,
                    ] : null,
                    'specialization' => $student->specialization ? [
                        'id' => $student->specialization->id,
                        'name' => $student->specialization->name,
                        'description' => $student->specialization->description ?? null,
                    ] : null,
                    'curriculum_version' => $student->curriculumVersion ? [
                        'id' => $student->curriculumVersion->id,
                        'version_name' => $student->curriculumVersion->version_name,
                        'is_active' => $student->curriculumVersion->is_active,
                    ] : null,
                    'contact_info' => [
                        'emergency_contact_name' => $student->emergency_contact_name,
                        'emergency_contact_phone' => $student->emergency_contact_phone,
                        'emergency_contact_relationship' => $student->emergency_contact_relationship,
                    ],
                    'admission_info' => [
                        'high_school_name' => $student->high_school_name,
                        'high_school_graduation_year' => $student->high_school_graduation_year,
                        'entrance_exam_score' => $student->entrance_exam_score,
                        'admission_notes' => $student->admission_notes,
                    ],
                ]
            ]
        ]);
    }

    /**
     * Update student profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $student = $request->user();

        $validator = Validator::make($request->all(), [
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $student->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'phone' => $student->phone,
                    'address' => $student->address,
                ]
            ]
        ]);
    }

    /**
     * Get academic record
     */
    public function academicRecord(Request $request): JsonResponse
    {
        $student = $request->user();

        $registrations = CourseRegistration::with([
            'courseOffering.curriculumUnit.unit',
            'semester'
        ])
            ->where('student_id', $student->id)
            ->orderBy('semester_id', 'desc')
            ->orderBy('registration_date', 'desc')
            ->get();

        // Group by semester
        $semesterRecords = $registrations->groupBy('semester_id')->map(function ($semesterRegistrations) {
            $semester = $semesterRegistrations->first()->semester;
            $courses = $semesterRegistrations->map(function ($registration) {
                return [
                    'id' => $registration->id,
                    'course_code' => $registration->courseOffering->course_code,
                    'course_title' => $registration->courseOffering->course_title,
                    'credit_hours' => $registration->credit_hours,
                    'final_grade' => $registration->final_grade,
                    'grade_points' => $registration->grade_points,
                    'registration_status' => $registration->registration_status,
                    'registration_date' => $registration->registration_date->format('Y-m-d'),
                    'is_retake' => $registration->is_retake,
                    'attempt_number' => $registration->attempt_number,
                ];
            });

            // Calculate semester GPA
            $completedCourses = $semesterRegistrations->where('registration_status', 'completed')
                ->whereNotNull('final_grade');

            $totalPoints = 0;
            $totalCredits = 0;

            foreach ($completedCourses as $course) {
                $gradePoints = $course->getGradePoints();
                $credits = $course->credit_hours;
                $totalPoints += $gradePoints * $credits;
                $totalCredits += $credits;
            }

            $semesterGPA = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.0;

            return [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                ],
                'courses' => $courses,
                'semester_gpa' => $semesterGPA,
                'total_credits' => $semesterRegistrations->sum('credit_hours'),
                'completed_credits' => $completedCourses->sum('credit_hours'),
            ];
        })->values();

        // Calculate cumulative GPA
        $allCompleted = $registrations->where('registration_status', 'completed')
            ->whereNotNull('final_grade');

        $cumulativeTotalPoints = 0;
        $cumulativeTotalCredits = 0;

        foreach ($allCompleted as $course) {
            $gradePoints = $course->getGradePoints();
            $credits = $course->credit_hours;
            $cumulativeTotalPoints += $gradePoints * $credits;
            $cumulativeTotalCredits += $credits;
        }

        $cumulativeGPA = $cumulativeTotalCredits > 0 ?
            round($cumulativeTotalPoints / $cumulativeTotalCredits, 2) : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'semester_records' => $semesterRecords,
                'summary' => [
                    'cumulative_gpa' => $cumulativeGPA,
                    'total_credits_attempted' => $registrations->sum('credit_hours'),
                    'total_credits_earned' => $allCompleted->sum('credit_hours'),
                    'total_courses' => $registrations->count(),
                    'completed_courses' => $allCompleted->count(),
                ]
            ]
        ]);
    }

    /**
     * Get student holds
     */
    public function holds(Request $request): JsonResponse
    {
        $student = $request->user();

        $holds = $student->academicHolds()
            ->with(['placedByUser', 'resolvedByUser'])
            ->orderBy('placed_date', 'desc')
            ->get()
            ->map(function ($hold) {
                return [
                    'id' => $hold->id,
                    'hold_type' => $hold->hold_type,
                    'hold_category' => $hold->hold_category,
                    'title' => $hold->title,
                    'description' => $hold->description,
                    'amount' => $hold->amount,
                    'priority' => $hold->priority,
                    'status' => $hold->status,
                    'placed_date' => $hold->placed_date->format('Y-m-d'),
                    'due_date' => $hold->due_date?->format('Y-m-d'),
                    'resolved_date' => $hold->resolved_date?->format('Y-m-d'),
                    'resolution_notes' => $hold->resolution_notes,
                    'blocks_registration' => $hold->blocksRegistration(),
                    'blocks_graduation' => $hold->blocksGraduation(),
                    'blocks_transcript' => $hold->blocksTranscript(),
                ];
            });

        $activeHolds = $holds->where('status', 'active');
        $registrationBlocks = $activeHolds->where('blocks_registration', true);

        return response()->json([
            'success' => true,
            'data' => [
                'holds' => $holds,
                'summary' => [
                    'total_holds' => $holds->count(),
                    'active_holds' => $activeHolds->count(),
                    'registration_blocks' => $registrationBlocks->count(),
                    'total_amount_owed' => $activeHolds->sum('amount'),
                    'can_register' => $registrationBlocks->isEmpty(),
                ]
            ]
        ]);
    }

    /**
     * Get graduation progress
     */
    public function graduationProgress(Request $request): JsonResponse
    {
        $student = $request->user();

        // Get graduation requirements
        $requirement = GraduationRequirement::where('program_id', $student->program_id)
            ->where('specialization_id', $student->specialization_id)
            ->currentlyEffective()
            ->first();

        if (!$requirement) {
            return response()->json([
                'success' => false,
                'message' => 'No graduation requirements found for your program'
            ], 404);
        }

        // Calculate completed credits
        $completedRegistrations = CourseRegistration::where('student_id', $student->id)
            ->where('registration_status', 'completed')
            ->passing()
            ->with('courseOffering.curriculumUnit.unit')
            ->get();

        $totalCreditsEarned = $completedRegistrations->sum('credit_hours');

        // TODO: Implement more detailed credit categorization (core, major, elective)
        // This would require additional data structure to categorize courses

        $progressPercentage = $requirement->total_credits_required > 0 ?
            round(($totalCreditsEarned / $requirement->total_credits_required) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'requirements' => [
                    'total_credits_required' => $requirement->total_credits_required,
                    'core_credits_required' => $requirement->core_credits_required,
                    'major_credits_required' => $requirement->major_credits_required,
                    'elective_credits_required' => $requirement->elective_credits_required,
                    'minimum_gpa' => $requirement->minimum_gpa,
                    'minimum_major_gpa' => $requirement->minimum_major_gpa,
                    'maximum_study_years' => $requirement->maximum_study_years,
                    'required_internship' => $requirement->required_internship,
                    'required_thesis' => $requirement->required_thesis,
                    'required_english_certification' => $requirement->required_english_certification,
                ],
                'progress' => [
                    'total_credits_earned' => $totalCreditsEarned,
                    'credits_remaining' => max(0, $requirement->total_credits_required - $totalCreditsEarned),
                    'progress_percentage' => $progressPercentage,
                    'completed_courses' => $completedRegistrations->count(),
                    'is_eligible_to_graduate' => $progressPercentage >= 100, // Simplified check
                ],
                'timeline' => [
                    'admission_date' => $student->admission_date?->format('Y-m-d'),
                    'expected_graduation_date' => $student->expected_graduation_date?->format('Y-m-d'),
                    'years_enrolled' => $student->admission_date ?
                        $student->admission_date->diffInYears(now()) : 0,
                ]
            ]
        ]);
    }
}
