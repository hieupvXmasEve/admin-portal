<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssessmentComponentDetailScore;
use App\Models\Attendance;
use App\Models\CourseOffering;
use App\Models\CurriculumVersion;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service class for handling Student Academic Summary business logic
 *
 * Aggregates and processes academic data from multiple sources to provide
 * comprehensive academic summaries including registrations, scores, attendance,
 * GPA calculations, and graduation progress tracking.
 */
class StudentAcademicSummaryService
{
    /**
     * Get comprehensive academic summary for a student with caching
     *
     * @param int $studentId The student ID to get summary for
     * @param array $filters Optional filters for specific data sections
     * @param bool $useCache Whether to use cache (default: true)
     * @return array Complete academic summary data
     */
    public function getAcademicSummary(int $studentId, array $filters = [], bool $useCache = true): array
    {
        try {
            // Create cache key based on student ID and filters
            $cacheKey = "student_academic_summary_{$studentId}_" . md5(serialize($filters));
            $cacheTtl = 3600; // 1 hour cache

            // Check cache if enabled and no specific filters (for performance)
            if ($useCache && empty($filters) && Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $student = Student::with([
                'campus:id,name,code',
                'program:id,name,code',
                'specialization:id,name,code',
                'curriculumVersion:id,version_code,program_id,specialization_id,semester_id',
                'curriculumVersion.program:id,name,code',
                'curriculumVersion.specialization:id,name,code',
                'curriculumVersion.effectiveFromSemester:id,start_date,code',
            ])->findOrFail($studentId);

            // Extract specific filters for each section
            $registrationFilters = $filters['registrations'] ?? [];
            $perPage = $filters['per_page'] ?? 50;

            $academicSummary = [
                'overview' => $this->getStudentOverview($student),
                'registrations' => $this->getRegistrations($student, $registrationFilters, $perPage),
                'scores' => $this->getScores($student, 100),
                'attendance' => $this->getAttendance($student),
                'gpa' => $this->getGpaTranscript($student),
                'graduation' => $this->getGraduationTracker($student),
            ];

            // Cache the result if no filters and caching is enabled
            //            if ($useCache && empty($filters)) {
            //                Cache::put($cacheKey, $academicSummary, $cacheTtl);
            //            }

            return $academicSummary;
        } catch (\Exception $e) {
            Log::error('Error getting academic summary', [
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Clear cached academic summary data for a student
     *
     * @param int $studentId The student ID
     */
    public function clearAcademicSummaryCache(int $studentId): void
    {
        $cachePattern = "student_academic_summary_{$studentId}_*";

        // Clear the main cache entry (no filters)
        $mainCacheKey = "student_academic_summary_{$studentId}_" . md5(serialize([]));
        Cache::forget($mainCacheKey);

        // Note: In production, you might want to use a more sophisticated cache tagging system
        // to clear all related cache entries efficiently
    }

    /**
     * Get student overview information
     *
     * @param Student $student The student model
     * @return array Student overview data
     */
    private function getStudentOverview(Student $student): array
    {
        // Get current GPA calculation
        $currentGpa = $student->gpaCalculations()
            ->where('is_current', true)
            ->orWhere('id', $student->gpaCalculations()->latest()->first()?->id)
            ->first();

        // Get current semester enrollment
        $currentSemester = Semester::where('is_active', true)->first();
        $currentEnrollment = null;
        if ($currentSemester) {
            $currentEnrollment = $student->courseRegistrations()
                ->where('semester_id', $currentSemester->id)
                ->whereIn('registration_status', ['enrolled', 'active'])
                ->count();
        }

        return [
            'student_info' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'date_of_birth' => $student->date_of_birth,
                'gender' => $student->gender,
                'nationality' => $student->nationality,
                'national_id' => $student->national_id,
                'address' => $student->address,
                'cccd_address' => $student->cccd_address,
                'status' => $student->status,
                'academic_status' => $student->academic_status,
                'admission_date' => $student->admission_date,
                'expected_graduation_date' => $student->expected_graduation_date,
                'status_change_date' => $student->status_change_date,
                'status_reason' => $student->status_reason,
                'avatar_url' => $student->avatar_url,

                'emergency_contact_name' => $student->emergency_contact_name,
                'emergency_contact_email' => $student->emergency_contact_email,
                'emergency_contact_phone' => $student->emergency_contact_phone,
                'emergency_contact_relationship' => $student->emergency_contact_relationship,

                'emergency_contact_name_1' => $student->emergency_contact_name_1,
                'emergency_contact_email_1' => $student->emergency_contact_email_1,
                'emergency_contact_phone_1' => $student->emergency_contact_phone_1,
                'emergency_contact_relationship_1' => $student->emergency_contact_relationship_1,

                'high_school_name' => $student->high_school_name,

            ],
            'program_info' => [
                'campus' => $student->campus ? [
                    'id' => $student->campus->id,
                    'name' => $student->campus->name,
                    'code' => $student->campus->code,
                ] : null,
                'program' => $student->program ? [
                    'id' => $student->program->id,
                    'name' => $student->program->name,
                    'code' => $student->program->code,
                ] : null,
                'specialization' => $student->specialization ? [
                    'id' => $student->specialization->id,
                    'name' => $student->specialization->name,
                    'code' => $student->specialization->code,
                ] : null,
                'curriculum_version' => $student->curriculumVersion ? [
                    'id' => $student->curriculumVersion->id,
                    'version_code' => $student->curriculumVersion->version_code,
                    'program_name' => $student->curriculumVersion->program?->name,
                    'program_code' => $student->curriculumVersion->program?->code,
                    'specialization_name' => $student->curriculumVersion->specialization?->name,
                    'specialization_code' => $student->curriculumVersion->specialization?->code,
                ] : null,
                'semester' => [
                    'code' => $student->curriculumVersion->effectiveFromSemester?->code,
                    'intake_year' => Carbon::parse($student->curriculumVersion->effectiveFromSemester?->start_date)->year,
                ]

            ],
            'academic_stats' => [
                'total_registrations' => $student->courseRegistrations()->count(),
                'completed_courses' => $student->courseRegistrations()
                    ->where('registration_status', 'completed')->count(),
                'active_registrations' => $student->courseRegistrations()
                    ->whereIn('registration_status', ['enrolled', 'active'])->count(),
                'current_semester_enrollments' => $currentEnrollment ?? 0,
                'total_credits_earned' => $student->academicRecords()
                    ->where('completion_status', 'completed')
                    ->where('grade_status', 'passing')
                    ->sum('credit_hours_earned'),
                'total_credits_attempted' => $student->academicRecords()
                    ->sum('credit_hours'),
                'current_gpa' => $currentGpa?->gpa ?? 0,
                'cumulative_gpa' => $currentGpa?->cumulative_gpa ?? $currentGpa?->gpa ?? 0,
                'academic_standing' => $currentGpa?->academic_standing ?? 'unknown',
                'active_holds' => $student->academicHolds()->where('status', 'active')->count(),
                'retake_courses' => $student->courseRegistrations()->where('is_retake', true)->count(),
            ],
            'additional_info' => [
                'high_school_name' => $student->high_school_name,
                'high_school_graduation_year' => $student->high_school_graduation_year,
                'entrance_exam_score' => $student->entrance_exam_score,
                'admission_notes' => $student->admission_notes,
            ],
        ];
    }

    /**
     * Get course registrations data with comprehensive filtering and pagination support
     *
     * @param Student $student The student model
     * @param array $filters Optional filters for semester, status, academic year
     * @param int $perPage Number of items per page for pagination
     * @return array Course registrations data with filtering and pagination
     */
    private function getRegistrations(Student $student, array $filters = [], int $perPage = 50): array
    {
        $query = $student->courseRegistrations()
            ->with([
                'courseOffering.curriculumUnit.unit:id,name,code,credit_points',
                'courseOffering.semester:id,name,code,start_date,end_date',
                'semester:id,name,code,start_date,end_date',
            ]);

        // Apply filters
        if (!empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (!empty($filters['academic_year'])) {
            $query->whereHas('semester', function ($q) use ($filters) {
                // We need to filter by academic year derived from semester data
                $q->where(function ($subQuery) use ($filters) {
                    $academicYear = $filters['academic_year'];
                    // Extract years from academic year format (e.g., "2024-2025" -> [2024, 2025])
                    if (preg_match('/(\d{4})-(\d{4})/', $academicYear, $matches)) {
                        $startYear = $matches[1];
                        $endYear = $matches[2];

                        // Match semesters that could belong to this academic year
                        $subQuery->where(function ($q) use ($endYear) {
                            // Spring semester of the academic year (contains end year)
                            $q->where('code', 'LIKE', '%SPR' . $endYear . '%')
                                ->orWhere('name', 'LIKE', '%Spring ' . $endYear . '%')
                                ->orWhere('name', 'LIKE', '%Spring' . $endYear . '%');
                        })->orWhere(function ($q) use ($startYear) {
                            // Fall semester of the academic year (contains start year)
                            $q->where('code', 'LIKE', '%FALL' . $startYear . '%')
                                ->orWhere('name', 'LIKE', '%Fall ' . $startYear . '%')
                                ->orWhere('name', 'LIKE', '%Fall' . $startYear . '%');
                        });
                    }
                });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('registration_status', $filters['status']);
        }

        if (!empty($filters['is_retake'])) {
            $query->where('is_retake', $filters['is_retake'] === 'true');
        }

        // Order by registration date (most recent first) and then by semester
        $query->orderBy('registration_date', 'desc')
            ->orderBy('semester_id', 'desc');

        // Get all registrations for summary calculations (without pagination)
        $allRegistrations = $query->get();

        // Get paginated results
        $paginatedRegistrations = $query->paginate($perPage);

        // Transform the data
        $registrations = $paginatedRegistrations->getCollection()->map(function ($registration) {
            $semester = $registration->semester ?? $registration->courseOffering->semester;
            $unit = $registration->courseOffering->curriculumUnit->unit ?? null;

            return [
                'id' => $registration->id,
                'course_name' => $unit->name ?? 'N/A',
                'course_code' => $unit->code ?? 'N/A',
                'credit_points' => $unit->credit_points ?? 0,
                'semester' => $semester->name ?? 'N/A',
                'semester_code' => $semester->code ?? 'N/A',
                'academic_year' => $this->getAcademicYear($semester),
                'semester_start_date' => $semester->start_date ?? null,
                'semester_end_date' => $semester->end_date ?? null,
                'registration_status' => $registration->registration_status,
                'registration_date' => $registration->registration_date,
                'registration_method' => $registration->registration_method ?? 'N/A',
                'final_grade' => $registration->final_grade,
                'grade_points' => $registration->grade_points,
                'credit_hours' => $registration->credit_hours,
                'is_retake' => $registration->is_retake,
                'attempt_number' => $registration->attempt_number,
                'completion_date' => $registration->completion_date,
                'drop_date' => $registration->drop_date,
                'withdrawal_date' => $registration->withdrawal_date,
                'retake_fee' => $registration->retake_fee ?? 0,
                'is_retake_paid' => $registration->is_retake_paid ?? 'no',
                'notes' => $registration->notes,
                'status_badge_color' => $this->getRegistrationStatusBadgeColor($registration->registration_status),
                'grade_badge_color' => $this->getGradeBadgeColor($registration->final_grade),
                'is_passing_grade' => $this->isPassingGrade($registration->final_grade),
                'formatted_registration_date' => $registration->registration_date ?
                    \Carbon\Carbon::parse($registration->registration_date)->format('M j, Y') : null,
                'formatted_completion_date' => $registration->completion_date ?
                    \Carbon\Carbon::parse($registration->completion_date)->format('M j, Y') : null,
            ];
        });

        // Get all registrations for summary calculations (without pagination)
        $allRegistrations = $student->courseRegistrations()->get();

        // Calculate summary statistics
        $summary = [
            'total_registrations' => $allRegistrations->count(),
            'completed' => $allRegistrations->where('registration_status', 'completed')->count(),
            'active' => $allRegistrations->whereIn('registration_status', ['enrolled', 'active', 'registered', 'confirmed'])->count(),
            'dropped' => $allRegistrations->where('registration_status', 'dropped')->count(),
            'withdrawn' => $allRegistrations->where('registration_status', 'withdrawn')->count(),
            'retakes' => $allRegistrations->where('is_retake', true)->count(),
            'total_credits_attempted' => $allRegistrations->sum('credit_hours'),
            'total_credits_earned' => $allRegistrations->where('registration_status', 'completed')
                ->where('final_grade', '!=', null)
                ->filter(function ($reg) {
                    return $this->isPassingGrade($reg->final_grade);
                })
                ->sum('credit_hours'),
            'completion_rate' => $allRegistrations->count() > 0 ?
                round(($allRegistrations->where('registration_status', 'completed')->count() / $allRegistrations->count()) * 100, 2) : 0,
            'retake_rate' => $allRegistrations->count() > 0 ?
                round(($allRegistrations->where('is_retake', true)->count() / $allRegistrations->count()) * 100, 2) : 0,
            'average_grade_points' => $allRegistrations->where('grade_points', '>', 0)->avg('grade_points') ?? 0,
        ];

        // Get semester groups for filtering
        $semesterGroups = $allRegistrations->groupBy(function ($registration) {
            $semester = $registration->semester ?? $registration->courseOffering->semester;

            return $this->getAcademicYear($semester);
        })->map(function ($registrations, $academicYear) {
            $semesters = $registrations->map(function ($registration) {
                $semester = $registration->semester ?? $registration->courseOffering->semester;

                return [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'academic_year' => $this->getAcademicYear($semester),
                ];
            })->unique('id')->values();

            return [
                'academic_year' => $academicYear,
                'semesters' => $semesters,
                'total_registrations' => $registrations->count(),
            ];
        })->sortByDesc('academic_year')->values();

        // Get status breakdown
        $statusBreakdown = $allRegistrations->groupBy('registration_status')->map(function ($registrations, $status) use ($allRegistrations) {
            return [
                'status' => $status,
                'count' => $registrations->count(),
                'percentage' => $allRegistrations->count() > 0 ?
                    round(($registrations->count() / $allRegistrations->count()) * 100, 2) : 0,
                'badge_color' => $this->getRegistrationStatusBadgeColor($status),
            ];
        })->values();

        return [
            'data' => $registrations,
            'pagination' => [
                'current_page' => $paginatedRegistrations->currentPage(),
                'last_page' => $paginatedRegistrations->lastPage(),
                'per_page' => $paginatedRegistrations->perPage(),
                'total' => $paginatedRegistrations->total(),
                'from' => $paginatedRegistrations->firstItem(),
                'to' => $paginatedRegistrations->lastItem(),
                'has_more_pages' => $paginatedRegistrations->hasMorePages(),
            ],
            'summary' => $summary,
            'semester_groups' => $semesterGroups,
            'status_breakdown' => $statusBreakdown,
            'filters' => $filters,
        ];
    }

    /**
     * Get badge color for registration status
     *
     * @param string $status Registration status
     * @return string CSS color class
     */
    private function getRegistrationStatusBadgeColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'enrolled', 'active', 'registered', 'confirmed' => 'primary',
            'dropped' => 'warning',
            'withdrawn' => 'destructive',
            default => 'secondary',
        };
    }

    /**
     * Get badge color for grade
     *
     * @param string|null $grade Final grade
     * @return string CSS color class
     */
    private function getGradeBadgeColor(?string $grade): string
    {
        if (!$grade) {
            return 'secondary';
        }

        // Assuming letter grades A-F
        return match (strtoupper($grade)) {
            'A', 'A+', 'A-' => 'success',
            'B', 'B+', 'B-' => 'primary',
            'C', 'C+', 'C-' => 'warning',
            'D', 'D+', 'D-' => 'warning',
            'F' => 'destructive',
            default => 'secondary',
        };
    }

    /**
     * Check if grade is passing
     *
     * @param string|null $grade Final grade
     * @return bool Whether grade is passing
     */
    private function isPassingGrade(?string $grade): bool
    {
        if (!$grade) {
            return false;
        }

        // Assuming D- and above are passing grades
        $passingGrades = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-'];

        return in_array(strtoupper($grade), $passingGrades);
    }

    /**
     * Derive academic year from semester data
     *
     * @param object|null $semester Semester model instance
     * @return string Academic year (e.g., '2024-2025')
     */
    private function getAcademicYear($semester): string
    {
        if (!$semester) {
            return 'Unknown';
        }

        // Try to extract from semester code first (e.g., 'SPR2025' -> '2024-2025')
        if ($semester->code) {
            if (preg_match('/(\d{4})/', $semester->code, $matches)) {
                $year = (int)$matches[1];
                // If it's a spring semester, it's the second year of the academic year
                if (str_contains(strtolower($semester->code), 'spr')) {
                    return ($year - 1) . '-' . $year;
                }

                // If it's a fall semester, it's the first year of the academic year
                return $year . '-' . ($year + 1);
            }
        }

        // Try to extract from semester name (e.g., 'Spring 2025' -> '2024-2025')
        if ($semester->name) {
            if (preg_match('/(\d{4})/', $semester->name, $matches)) {
                $year = (int)$matches[1];
                // If it's a spring semester, it's the second year of the academic year
                if (str_contains(strtolower($semester->name), 'spring')) {
                    return ($year - 1) . '-' . $year;
                }

                // If it's a fall semester, it's the first year of the academic year
                return $year . '-' . ($year + 1);
            }
        }

        // Try to derive from start_date
        if ($semester->start_date) {
            $startYear = \Carbon\Carbon::parse($semester->start_date)->year;
            $startMonth = \Carbon\Carbon::parse($semester->start_date)->month;

            // If semester starts in Jan-Jul, it's likely the second year of the academic year
            if ($startMonth <= 7) {
                return ($startYear - 1) . '-' . $startYear;
            }

            // If semester starts in Aug-Dec, it's likely the first year of the academic year
            return $startYear . '-' . ($startYear + 1);
        }

        return 'Unknown';
    }

    /**
     * Get assessment scores data grouped by course offering with pagination support
     *
     * @param Student $student The student model
     * @param int $limit Limit for recent scores per course (default: 5)
     * @return array Assessment scores data
     */
    private function getScores(Student $student, int $limit = 100): array
    {
        $scores = AssessmentComponentDetailScore::where('student_id', $student->id)
            ->with([
                'courseOffering.curriculumUnit.unit:id,name,code',
                'courseOffering.semester:id,name,code',
                'assessmentComponentDetail.assessmentComponent:id,name,type,weight',
                'assessmentComponentDetail:id,assessment_component_id,name,description,due_date,max_points',
            ])
            ->orderBy('graded_at', 'desc')
            ->get()
            ->groupBy('course_offering_id')
            ->map(function ($courseScores, $courseOfferingId) use ($limit) {
                $firstScore = $courseScores->first();
                Log::info('courseScores', [
                    'courseScores' => $courseScores->count(),
                    'courseOfferingId' => $courseOfferingId,
                    'limit' => $limit,
                ]);

                // Map all scores for metadata calculation
                $allScores = $courseScores->map(function ($score) {
                    return [
                        'id' => $score->id,
                        'assessment_name' => $score->assessmentComponentDetail->name ?? 'N/A',
                        'assessment_type' => $score->assessmentComponentDetail->assessmentComponent->type ?? 'N/A',
                        'due_date' => $score->assessmentComponentDetail->due_date,
                        'max_points' => $score->assessmentComponentDetail->max_points,
                        'points_earned' => $score->points_earned,
                        'percentage_score' => $score->percentage_score,
                        'letter_grade' => $score->letter_grade,
                        'gpa_points' => $score->gpa_points,
                        'submitted_at' => $score->submitted_at,
                        'graded_at' => $score->graded_at,
                        'is_late' => $score->is_late,
                        'status' => $score->status,
                    ];
                });

                return [
                    'course_offering_id' => $courseOfferingId,
                    'course_name' => $firstScore->courseOffering->curriculumUnit->unit->name ?? 'N/A',
                    'course_code' => $firstScore->courseOffering->curriculumUnit->unit->code ?? 'N/A',
                    'semester' => $firstScore->courseOffering->semester->name ?? 'N/A',
                    'scores' => $allScores, // Limit for initial display
                    'all_scores_count' => $allScores->count(),
                    'has_more_scores' => $allScores->count() > $limit,
                    'course_average' => $courseScores->avg('percentage_score'),
                    'total_assessments' => $courseScores->count(),
                    'completed_assessments' => $courseScores->where('status', 'graded')->count(),
                ];
            })
            ->values();

        return [
            'data' => $scores,
            'summary' => [
                'total_courses' => $scores->count(),
                'total_assessments' => $scores->sum('total_assessments'),
                'completed_assessments' => $scores->sum('completed_assessments'),
                'overall_average' => $scores->avg('course_average'),
            ],
        ];
    }

    /**
     * Get student overview data
     *
     * @param Student $student The student model
     * @return array Overview data
     */
    public function getOverviewData(Student $student): array
    {
        return $this->getStudentOverview($student);
    }

    /**
     * Get registrations data
     *
     * @param Student $student The student model
     * @param array $filters Optional filters
     * @param int $perPage Items per page
     * @return array Registrations data
     */
    public function getRegistrationsData(Student $student, array $filters = [], int $perPage = 50): array
    {
        return $this->getRegistrations($student, $filters, $perPage);
    }

    /**
     * Get scores data
     *
     * @param Student $student The student model
     * @param int $limit Score limit
     * @return array Scores data
     */
    public function getScoresData(Student $student, int $limit = 100): array
    {
        return $this->getScores($student, $limit);
    }

    /**
     * Get attendance data
     *
     * @param Student $student The student model
     * @return array Attendance data
     */
    public function getAttendanceData(Student $student): array
    {
        return $this->getAttendance($student);
    }

    /**
     * Get GPA data
     *
     * @param Student $student The student model
     * @return array GPA data
     */
    public function getGpaData(Student $student): array
    {
        return $this->getGpaTranscript($student);
    }

    /**
     * Get graduation data
     *
     * @param Student $student The student model
     * @return array Graduation data
     */
    public function getGraduationData(Student $student): array
    {
        return $this->getGraduationTracker($student);
    }

    /**
     * Get detailed scores for a specific course offering (for lazy loading)
     *
     * @param Student $student The student model
     * @param int $courseOfferingId The course offering ID
     * @param int $offset Offset for pagination
     * @param int $limit Limit for pagination
     * @return array Detailed scores data
     */
    public function getCourseScoresDetails(Student $student, int $courseOfferingId, int $offset = 0, int $limit = 20): array
    {
        $scores = AssessmentComponentDetailScore::where('student_id', $student->id)
            ->where('course_offering_id', $courseOfferingId)
            ->with([
                'courseOffering.curriculumUnit.unit:id,name,code',
                'courseOffering.semester:id,name,code',
                'assessmentComponentDetail.assessmentComponent:id,name,type,weight',
                'assessmentComponentDetail:id,assessment_component_id,name,description,due_date,max_points',
            ])
            ->orderBy('graded_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $totalCount = AssessmentComponentDetailScore::where('student_id', $student->id)
            ->where('course_offering_id', $courseOfferingId)
            ->count();

        return [
            'data' => $scores->map(function ($score) {
                return [
                    'id' => $score->id,
                    'assessment_name' => $score->assessmentComponentDetail->name ?? 'N/A',
                    'assessment_type' => $score->assessmentComponentDetail->assessmentComponent->type ?? 'N/A',
                    'due_date' => $score->assessmentComponentDetail->due_date,
                    'max_points' => $score->assessmentComponentDetail->max_points,
                    'points_earned' => $score->points_earned,
                    'percentage_score' => $score->percentage_score,
                    'letter_grade' => $score->letter_grade,
                    'gpa_points' => $score->gpa_points,
                    'submitted_at' => $score->submitted_at,
                    'graded_at' => $score->graded_at,
                    'is_late' => $score->is_late,
                    'status' => $score->status,
                ];
            }),
            'pagination' => [
                'total' => $totalCount,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $totalCount,
            ],
        ];
    }

    /**
     * Get attendance data summarized by unit
     *
     * @param Student $student The student model
     * @return array Attendance summary data
     */
    private function getAttendance(Student $student): array
    {
        // Get attendance records with related data
        $attendanceData = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->join('curriculum_units', 'course_offerings.curriculum_unit_id', '=', 'curriculum_units.id')
            ->join('units', 'curriculum_units.unit_id', '=', 'units.id')
            ->join('semesters', 'course_offerings.semester_id', '=', 'semesters.id')
            ->where('attendances.student_id', $student->id)
            ->select([
                'units.id as unit_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'semesters.name as semester_name',
                'course_offerings.id as course_offering_id',
                'attendances.status',
                'attendances.check_in_time',
                'attendances.minutes_late',
                'class_sessions.session_date',
                'class_sessions.start_time',
                'class_sessions.end_time',
            ])
            ->orderBy('class_sessions.session_date', 'desc')
            ->get()
            ->groupBy('unit_id');

        $attendanceSummary = $attendanceData->map(function ($unitAttendance, $unitId) {
            $totalSessions = $unitAttendance->count();
            $presentCount = $unitAttendance->where('status', 'present')->count();
            $lateCount = $unitAttendance->where('status', 'late')->count();
            $absentCount = $unitAttendance->where('status', 'absent')->count();
            $excusedCount = $unitAttendance->where('status', 'excused')->count();

            $attendedCount = $presentCount + $lateCount; // Late is still considered attended
            $attendancePercentage = $totalSessions > 0 ? ($attendedCount / $totalSessions) * 100 : 0;

            $firstRecord = $unitAttendance->first();

            return [
                'unit_id' => $unitId,
                'unit_name' => $firstRecord->unit_name,
                'unit_code' => $firstRecord->unit_code,
                'semester' => $firstRecord->semester_name,
                'course_offering_id' => $firstRecord->course_offering_id,
                'total_sessions' => $totalSessions,
                'attended_count' => $attendedCount,
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'absent_count' => $absentCount,
                'excused_count' => $excusedCount,
                'attendance_percentage' => round($attendancePercentage, 2),
                'attendance_status' => $this->getAttendanceStatus($attendancePercentage),
                'sessions' => $unitAttendance->map(function ($session) {
                    return [
                        'session_date' => $session->session_date,
                        'start_time' => $session->start_time,
                        'end_time' => $session->end_time,
                        'status' => $session->status,
                        'check_in_time' => $session->check_in_time,
                        'minutes_late' => $session->minutes_late,
                    ];
                })->values(),
            ];
        })->values();

        return [
            'data' => $attendanceSummary,
            'summary' => [
                'total_units' => $attendanceSummary->count(),
                'total_sessions' => $attendanceSummary->sum('total_sessions'),
                'total_attended' => $attendanceSummary->sum('attended_count'),
                'total_absent' => $attendanceSummary->sum('absent_count'),
                'overall_percentage' => $attendanceSummary->count() > 0
                    ? round($attendanceSummary->avg('attendance_percentage'), 2)
                    : 0,
                'units_at_risk' => $attendanceSummary->where('attendance_percentage', '<', 80)->count(),
            ],
        ];
    }

    /**
     * Get attendance status based on percentage
     *
     * @param float $percentage Attendance percentage
     * @return string Status indicator
     */
    private function getAttendanceStatus(float $percentage): string
    {
        if ($percentage >= 90) {
            return 'excellent';
        }
        if ($percentage >= 80) {
            return 'good';
        }
        if ($percentage >= 70) {
            return 'warning';
        }

        return 'critical';
    }

    /**
     * Get GPA and transcript data
     *
     * @param Student $student The student model
     * @return array GPA and transcript data
     */
    private function getGpaTranscript(Student $student): array
    {
        $gpaCalculations = $student->gpaCalculations()
            ->with(['semester:id,name,code,start_date,end_date'])
            ->orderBy('academic_year', 'desc')
            ->orderBy('semester_id', 'desc')
            ->get()
            ->map(function ($gpa) {
                return [
                    'id' => $gpa->id,
                    'semester' => $gpa->semester->name ?? 'N/A',
                    'semester_code' => $gpa->semester->code ?? 'N/A',
                    'academic_year' => $gpa->academic_year,
                    'semester_gpa' => $gpa->gpa,
                    'cumulative_gpa' => $gpa->cumulative_gpa ?? $gpa->gpa,
                    'credit_hours_attempted' => $gpa->credit_hours_attempted,
                    'credit_hours_earned' => $gpa->credit_hours_earned,
                    'quality_points' => $gpa->quality_points,
                    'academic_standing' => $gpa->academic_standing,
                    'dean_list_eligible' => $gpa->dean_list_eligible ?? false,
                    'honors_eligible' => $gpa->honors_eligible ?? false,
                    'probation_status' => $gpa->academic_standing === 'probation',
                    'warning_status' => $gpa->academic_standing === 'warning',
                    'calculation_date' => $gpa->created_at,
                ];
            });

        $currentGpa = $gpaCalculations->first();

        return [
            'data' => $gpaCalculations,
            'summary' => [
                'current_semester_gpa' => $currentGpa->semester_gpa ?? 0,
                'current_cumulative_gpa' => $currentGpa->cumulative_gpa ?? 0,
                'total_credit_hours_earned' => $gpaCalculations->sum('credit_hours_earned'),
                'total_quality_points' => $gpaCalculations->sum('quality_points'),
                'current_academic_standing' => $currentGpa->academic_standing ?? 'unknown',
                'dean_list_semesters' => $gpaCalculations->where('dean_list_eligible', true)->count(),
                'probation_semesters' => $gpaCalculations->where('probation_status', true)->count(),
                'warning_semesters' => $gpaCalculations->where('warning_status', true)->count(),
                'gpa_trend' => $this->calculateGpaTrend($gpaCalculations),
            ],
        ];
    }

    /**
     * Calculate GPA trend over time
     *
     * @param \Illuminate\Support\Collection $gpaCalculations
     * @return string Trend indicator
     */
    private function calculateGpaTrend($gpaCalculations): string
    {
        if ($gpaCalculations->count() < 2) {
            return 'insufficient_data';
        }

        $recent = $gpaCalculations->take(3)->pluck('semester_gpa');
        $older = $gpaCalculations->skip(3)->take(3)->pluck('semester_gpa');

        if ($recent->isEmpty() || $older->isEmpty()) {
            return 'insufficient_data';
        }

        $recentAvg = $recent->avg();
        $olderAvg = $older->avg();

        $difference = $recentAvg - $olderAvg;

        if ($difference > 0.2) {
            return 'improving';
        }
        if ($difference < -0.2) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Get graduation progress tracking data
     *
     * @param Student $student The student model
     * @return array Graduation progress data
     */
    private function getGraduationTracker(Student $student): array
    {
        // Get curriculum requirements
        $curriculumUnits = $student->curriculumVersion
            ? $student->curriculumVersion->curriculumUnits()->with('unit')->get()
            : collect();

        // Get completed academic records
        $completedRecords = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->where('grade_status', 'passing')
            ->with('unit')
            ->get();

        // Calculate credit requirements
        $totalCreditsRequired = $curriculumUnits->sum(function ($curriculumUnit) {
            return $curriculumUnit->unit ? $curriculumUnit->unit->credit_points : 0;
        });
        $totalCreditsEarned = $completedRecords->sum('credit_hours_earned');
        $creditsRemaining = max(0, $totalCreditsRequired - $totalCreditsEarned);

        // Check specific requirements (simplified - would need more complex logic in real implementation)
        $requirements = [
            'core_credits' => [
                'required' => $curriculumUnits->where('type', 'core')->sum(function ($curriculumUnit) {
                    return $curriculumUnit->unit ? $curriculumUnit->unit->credit_points : 0;
                }),
                'earned' => $completedRecords->whereIn(
                    'unit_id',
                    $curriculumUnits->where('type', 'core')->pluck('unit_id')
                )->sum('credit_hours_earned'),
                'status' => 'in_progress', // Would calculate based on actual completion
            ],
            'elective_credits' => [
                'required' => $curriculumUnits->where('type', 'elective')->sum(function ($curriculumUnit) {
                    return $curriculumUnit->unit ? $curriculumUnit->unit->credit_points : 0;
                }),
                'earned' => $completedRecords->whereIn(
                    'unit_id',
                    $curriculumUnits->where('type', 'elective')->pluck('unit_id')
                )->sum('credit_hours_earned'),
                'status' => 'in_progress',
            ],
            'internship' => [
                'required' => true,
                'completed' => $completedRecords->where(function ($record) {
                    return $record->unit && str_contains($record->unit->code, 'INTERN');
                })->isNotEmpty(),
                'status' => $completedRecords->where(function ($record) {
                    return $record->unit && str_contains($record->unit->code, 'INTERN');
                })->isNotEmpty() ? 'completed' : 'pending',
            ],
            'thesis' => [
                'required' => true,
                'completed' => $completedRecords->where(function ($record) {
                    return $record->unit && str_contains($record->unit->code, 'THESIS');
                })->isNotEmpty(),
                'status' => $completedRecords->where(function ($record) {
                    return $record->unit && str_contains($record->unit->code, 'THESIS');
                })->isNotEmpty() ? 'completed' : 'pending',
            ],
            'english_requirement' => [
                'required' => true,
                'completed' => $completedRecords->where(function ($record) {
                    return $record->unit && str_contains($record->unit->code, 'ENG');
                })->isNotEmpty(),
                'status' => $completedRecords->where(function ($record) {
                    return $record->unit && str_contains($record->unit->code, 'ENG');
                })->isNotEmpty() ? 'completed' : 'pending',
            ],
        ];

        // Calculate completion percentage
        $completionPercentage = $totalCreditsRequired > 0
            ? round(($totalCreditsEarned / $totalCreditsRequired) * 100, 2)
            : 0;

        // Determine graduation readiness
        $graduationReady = $completionPercentage >= 100 &&
            $requirements['internship']['completed'] &&
            $requirements['thesis']['completed'] &&
            $requirements['english_requirement']['completed'];

        // Identify risks
        $risks = [];
        if ($completionPercentage < 50) {
            $risks[] = 'low_credit_completion';
        }
        if (!$requirements['internship']['completed']) {
            $risks[] = 'internship_pending';
        }
        if (!$requirements['thesis']['completed']) {
            $risks[] = 'thesis_pending';
        }
        if (!$requirements['english_requirement']['completed']) {
            $risks[] = 'english_requirement_pending';
        }

        return [
            'credit_summary' => [
                'total_required' => $totalCreditsRequired,
                'total_earned' => $totalCreditsEarned,
                'remaining' => $creditsRemaining,
                'completion_percentage' => $completionPercentage,
            ],
            'requirements' => $requirements,
            'graduation_status' => [
                'ready_to_graduate' => $graduationReady,
                'expected_graduation' => $student->expected_graduation_date,
                'risks' => $risks,
                'risk_level' => count($risks) > 2 ? 'high' : (count($risks) > 0 ? 'medium' : 'low'),
            ],
            'progress_timeline' => [
                'current_semester' => $this->getCurrentSemesterProgress($student),
                'projected_completion' => $this->calculateProjectedCompletion($student, $creditsRemaining),
            ],
        ];
    }

    /**
     * Get current semester progress
     *
     * @param Student $student The student model
     * @return array Current semester progress data
     */
    private function getCurrentSemesterProgress(Student $student): array
    {
        $currentSemester = Semester::where('is_active', true)->first();

        if (!$currentSemester) {
            return ['semester' => null, 'enrolled_credits' => 0, 'status' => 'no_current_semester'];
        }

        $currentRegistrations = $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->whereIn('registration_status', ['enrolled', 'active'])
            ->sum('credit_hours');

        return [
            'semester' => $currentSemester->name,
            'enrolled_credits' => $currentRegistrations,
            'status' => $currentRegistrations > 0 ? 'enrolled' : 'not_enrolled',
        ];
    }

    /**
     * Calculate projected completion date
     *
     * @param Student $student The student model
     * @param float $creditsRemaining Remaining credits to complete
     * @return array Projected completion data
     */
    private function calculateProjectedCompletion(Student $student, float $creditsRemaining): array
    {
        // Simple calculation - assumes 15 credits per semester
        $averageCreditsPerSemester = 15;
        $semestersRemaining = ceil($creditsRemaining / $averageCreditsPerSemester);

        $projectedDate = now()->addMonths($semestersRemaining * 6); // Assuming 6 months per semester

        return [
            'semesters_remaining' => $semestersRemaining,
            'projected_date' => $projectedDate->format('Y-m-d'),
            'on_track' => $projectedDate <= ($student->expected_graduation_date ?
                \Carbon\Carbon::parse($student->expected_graduation_date) :
                now()->addYears(2)),
        ];
    }

    /**
     * Filter academic data by semester
     *
     * @param int $studentId The student ID
     * @param int $semesterId The semester ID to filter by
     * @return array Filtered academic data
     */
    public function filterBySemester(int $studentId, int $semesterId): array
    {
        $student = Student::findOrFail($studentId);

        // Filter registrations by semester
        $registrations = $student->courseRegistrations()
            ->where('semester_id', $semesterId)
            ->with([
                'courseOffering.curriculumUnit.unit:id,name,code,credit_points',
                'courseOffering.semester:id,name,code,start_date,end_date',
            ])
            ->get()
            ->map(function ($registration) {
                return [
                    'id' => $registration->id,
                    'course_name' => $registration->courseOffering->curriculumUnit->unit->name ?? 'N/A',
                    'course_code' => $registration->courseOffering->curriculumUnit->unit->code ?? 'N/A',
                    'registration_status' => $registration->registration_status,
                    'final_grade' => $registration->final_grade,
                    'credit_hours' => $registration->credit_hours,
                    'is_retake' => $registration->is_retake,
                    'attempt_number' => $registration->attempt_number,
                ];
            });

        // Filter scores by semester
        $scores = AssessmentComponentDetailScore::where('student_id', $studentId)
            ->whereHas('courseOffering', function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId);
            })
            ->with([
                'courseOffering.curriculumUnit.unit:id,name,code',
                'assessmentComponentDetail.assessmentComponent:id,name,type',
                'assessmentComponentDetail:id,assessment_component_id,name,due_date,max_points',
            ])
            ->get()
            ->groupBy('course_offering_id')
            ->map(function ($courseScores) {
                $firstScore = $courseScores->first();

                return [
                    'course_name' => $firstScore->courseOffering->curriculumUnit->unit->name ?? 'N/A',
                    'course_code' => $firstScore->courseOffering->curriculumUnit->unit->code ?? 'N/A',
                    'scores' => $courseScores->map(function ($score) {
                        return [
                            'assessment_name' => $score->assessmentComponentDetail->name ?? 'N/A',
                            'assessment_type' => $score->assessmentComponentDetail->assessmentComponent->type ?? 'N/A',
                            'percentage_score' => $score->percentage_score,
                            'letter_grade' => $score->letter_grade,
                            'graded_at' => $score->graded_at,
                        ];
                    }),
                    'course_average' => $courseScores->avg('percentage_score'),
                ];
            })
            ->values();

        return [
            'registrations' => $registrations,
            'scores' => $scores,
            'semester_info' => Semester::find($semesterId, ['id', 'name', 'code', 'start_date', 'end_date']),
        ];
    }

    /**
     * Filter academic data by course offering
     *
     * @param int $studentId The student ID
     * @param int $courseOfferingId The course offering ID to filter by
     * @return array Filtered academic data
     */
    public function filterByCourseOffering(int $studentId, int $courseOfferingId): array
    {
        $student = Student::findOrFail($studentId);
        $courseOffering = CourseOffering::with(['curriculumUnit.unit:id,name,code', 'semester:id,name,code'])
            ->findOrFail($courseOfferingId);

        // Get registration for this course offering
        $registration = $student->courseRegistrations()
            ->where('course_offering_id', $courseOfferingId)
            ->first();

        // Get scores for this course offering
        $scores = AssessmentComponentDetailScore::where('student_id', $studentId)
            ->where('course_offering_id', $courseOfferingId)
            ->with([
                'assessmentComponentDetail.assessmentComponent:id,name,type,weight',
                'assessmentComponentDetail:id,assessment_component_id,name,description,due_date,max_points',
            ])
            ->orderBy('graded_at', 'desc')
            ->get()
            ->map(function ($score) {
                return [
                    'id' => $score->id,
                    'assessment_name' => $score->assessmentComponentDetail->name ?? 'N/A',
                    'assessment_type' => $score->assessmentComponentDetail->assessmentComponent->type ?? 'N/A',
                    'weight' => $score->assessmentComponentDetail->assessmentComponent->weight ?? 0,
                    'due_date' => $score->assessmentComponentDetail->due_date,
                    'max_points' => $score->assessmentComponentDetail->max_points,
                    'points_earned' => $score->points_earned,
                    'percentage_score' => $score->percentage_score,
                    'letter_grade' => $score->letter_grade,
                    'submitted_at' => $score->submitted_at,
                    'graded_at' => $score->graded_at,
                    'is_late' => $score->is_late,
                    'status' => $score->status,
                ];
            });

        return [
            'course_info' => [
                'id' => $courseOffering->id,
                'name' => $courseOffering->curriculumUnit->unit->name ?? 'N/A',
                'code' => $courseOffering->curriculumUnit->unit->code ?? 'N/A',
                'semester' => $courseOffering->semester->name ?? 'N/A',
            ],
            'registration' => $registration ? [
                'registration_status' => $registration->registration_status,
                'final_grade' => $registration->final_grade,
                'credit_hours' => $registration->credit_hours,
                'is_retake' => $registration->is_retake,
                'attempt_number' => $registration->attempt_number,
            ] : null,
            'scores' => $scores,
            'score_summary' => [
                'total_assessments' => $scores->count(),
                'completed_assessments' => $scores->where('status', 'graded')->count(),
                'average_score' => $scores->where('status', 'graded')->avg('percentage_score'),
                'highest_score' => $scores->where('status', 'graded')->max('percentage_score'),
                'lowest_score' => $scores->where('status', 'graded')->min('percentage_score'),
            ],
        ];
    }

    /**
     * Get detailed attendance information for a specific unit
     *
     * @param int $studentId The student ID
     * @param int $unitId The unit ID
     * @param int|null $semesterId Optional semester filter
     * @return array Detailed attendance data
     */
    public function getAttendanceDetails(int $studentId, int $unitId, ?int $semesterId = null): array
    {
        $query = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->join('curriculum_units', 'course_offerings.curriculum_unit_id', '=', 'curriculum_units.id')
            ->join('units', 'curriculum_units.unit_id', '=', 'units.id')
            ->join('semesters', 'course_offerings.semester_id', '=', 'semesters.id')
            ->where('attendances.student_id', $studentId)
            ->where('units.id', $unitId);

        if ($semesterId) {
            $query->where('course_offerings.semester_id', $semesterId);
        }

        $attendanceRecords = $query->select([
            'class_sessions.id as session_id',
            'class_sessions.session_date',
            'class_sessions.start_time',
            'class_sessions.end_time',
            'class_sessions.session_type',
            'attendances.status',
            'attendances.check_in_time',
            'attendances.check_out_time',
            'attendances.minutes_late',
            'attendances.excuse_reason',
            'units.name as unit_name',
            'units.code as unit_code',
            'semesters.name as semester_name',
        ])
            ->orderBy('class_sessions.session_date', 'desc')
            ->get();

        $summary = [
            'total_sessions' => $attendanceRecords->count(),
            'present' => $attendanceRecords->where('status', 'present')->count(),
            'late' => $attendanceRecords->where('status', 'late')->count(),
            'absent' => $attendanceRecords->where('status', 'absent')->count(),
            'excused' => $attendanceRecords->where('status', 'excused')->count(),
        ];

        $summary['attended'] = $summary['present'] + $summary['late'];
        $summary['attendance_percentage'] = $summary['total_sessions'] > 0
            ? round(($summary['attended'] / $summary['total_sessions']) * 100, 2)
            : 0;

        return [
            'unit_info' => [
                'name' => $attendanceRecords->first()->unit_name ?? 'N/A',
                'code' => $attendanceRecords->first()->unit_code ?? 'N/A',
                'semester' => $attendanceRecords->first()->semester_name ?? 'N/A',
            ],
            'summary' => $summary,
            'sessions' => $attendanceRecords->map(function ($record) {
                return [
                    'session_id' => $record->session_id,
                    'session_date' => $record->session_date,
                    'start_time' => $record->start_time,
                    'end_time' => $record->end_time,
                    'session_type' => $record->session_type,
                    'status' => $record->status,
                    'check_in_time' => $record->check_in_time,
                    'check_out_time' => $record->check_out_time,
                    'minutes_late' => $record->minutes_late,
                    'excuse_reason' => $record->excuse_reason,
                ];
            }),
        ];
    }

    /**
     * Get detailed score breakdown for a specific course offering
     *
     * @param int $studentId The student ID
     * @param int $courseOfferingId The course offering ID
     * @return array Detailed score breakdown
     */
    public function getScoreDetails(int $studentId, int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::with(['curriculumUnit.unit:id,name,code', 'semester:id,name,code'])
            ->findOrFail($courseOfferingId);

        $scores = AssessmentComponentDetailScore::where('student_id', $studentId)
            ->where('course_offering_id', $courseOfferingId)
            ->with([
                'assessmentComponentDetail.assessmentComponent:id,name,type,weight,description',
                'assessmentComponentDetail:id,assessment_component_id,name,description,due_date,max_points,instructions',
            ])
            ->orderBy('assessmentComponentDetail.due_date', 'asc')
            ->get()
            ->groupBy('assessmentComponentDetail.assessment_component_id')
            ->map(function ($componentScores, $componentId) {
                $firstScore = $componentScores->first();
                $component = $firstScore->assessmentComponentDetail->assessmentComponent;

                return [
                    'assessment_component_id' => $componentId,
                    'component_name' => $component->name ?? 'N/A',
                    'component_type' => $component->type ?? 'N/A',
                    'component_weight' => $component->weight ?? 0,
                    'component_description' => $component->description,
                    'details' => $componentScores->map(function ($score) {
                        return [
                            'id' => $score->id,
                            'name' => $score->assessmentComponentDetail->name ?? 'N/A',
                            'description' => $score->assessmentComponentDetail->description,
                            'due_date' => $score->assessmentComponentDetail->due_date,
                            'max_points' => $score->assessmentComponentDetail->max_points,
                            'points_earned' => $score->points_earned,
                            'percentage_score' => $score->percentage_score,
                            'letter_grade' => $score->letter_grade,
                            'submitted_at' => $score->submitted_at,
                            'graded_at' => $score->graded_at,
                            'is_late' => $score->is_late,
                            'minutes_late' => $score->minutes_late,
                            'status' => $score->status,
                            'instructor_feedback' => $score->instructor_feedback,
                        ];
                    }),
                    'component_average' => $componentScores->avg('percentage_score'),
                    'component_total_points' => $componentScores->sum('points_earned'),
                    'component_max_points' => $componentScores->sum(function ($score) {
                        return $score->assessmentComponentDetail->max_points ?? 0;
                    }),
                ];
            })
            ->values();

        return [
            'course_info' => [
                'id' => $courseOffering->id,
                'name' => $courseOffering->curriculumUnit->unit->name ?? 'N/A',
                'code' => $courseOffering->curriculumUnit->unit->code ?? 'N/A',
                'semester' => $courseOffering->semester->name ?? 'N/A',
            ],
            'assessment_components' => $scores,
            'overall_summary' => [
                'total_components' => $scores->count(),
                'completed_components' => $scores->where('component_average', '>', 0)->count(),
                'overall_average' => $scores->avg('component_average'),
                'weighted_average' => $this->calculateWeightedAverage($scores),
            ],
        ];
    }

    /**
     * Calculate weighted average for assessment components
     *
     * @param \Illuminate\Support\Collection $components
     * @return float Weighted average
     */
    private function calculateWeightedAverage($components): float
    {
        $totalWeight = $components->sum('component_weight');

        if ($totalWeight == 0) {
            return $components->avg('component_average') ?? 0;
        }

        $weightedSum = $components->sum(function ($component) {
            return ($component['component_average'] ?? 0) * ($component['component_weight'] ?? 0);
        });

        return round($weightedSum / $totalWeight, 2);
    }
}
