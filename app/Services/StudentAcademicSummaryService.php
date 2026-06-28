<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssessmentComponentDetailScore;
use App\Models\Attendance;
use App\Models\CourseOffering;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * Get student overview information
     *
     * @param  Student  $student  The student model
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

        // Recent course registrations (last 5) — folded in from the retired Show page.
        $recentRegistrations = $student->courseRegistrations()
            ->with([
                'courseOffering.unit:id,name,code,credit_points',
                'courseOffering.semester:id,name,code',
            ])
            ->orderByDesc('registration_date')
            ->limit(5)
            ->get()
            ->map(function ($registration) {
                $unit = $registration->courseOffering?->unit;
                $semester = $registration->courseOffering?->semester;

                return [
                    'id' => $registration->id,
                    'course_offering_id' => $registration->course_offering_id,
                    'unit_name' => $unit?->name,
                    'unit_code' => $unit?->code,
                    'credit_points' => $unit?->credit_points,
                    'semester' => $semester?->name,
                    'registration_status' => $registration->registration_status,
                    'registration_date' => $registration->registration_date,
                ];
            })
            ->values()
            ->all();

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
                'ethnicity' => $student->ethnicity,
                'national_id' => $student->national_id,
                'address' => $student->address,
                'current_address_line' => $student->current_address_line,
                'current_ward' => $student->current_ward,
                'current_province' => $student->current_province,
                'current_country' => $student->current_country,
                'cccd_address' => $student->cccd_address,
                'cccd_address_line' => $student->cccd_address_line,
                'cccd_ward' => $student->cccd_ward,
                'cccd_province' => $student->cccd_province,
                'cccd_country' => $student->cccd_country,
                'status' => $student->status,
                'academic_status' => $student->academic_status,
                'admission_date' => $student->admission_date,
                'expected_graduation_date' => $student->expected_graduation_date,
                'status_change_date' => $student->status_change_date,
                'status_reason' => $student->status_reason,
                'avatar_url' => $student->avatar_url,
                'intake_mode' => $student->intake_mode,

                'emergency_contact_name' => $student->emergency_contact_name,
                'emergency_contact_email' => $student->emergency_contact_email,
                'emergency_contact_phone' => $student->emergency_contact_phone,
                'emergency_contact_relationship' => $student->emergency_contact_relationship,

                'emergency_contact_name_1' => $student->emergency_contact_name_1,
                'emergency_contact_email_1' => $student->emergency_contact_email_1,
                'emergency_contact_phone_1' => $student->emergency_contact_phone_1,
                'emergency_contact_relationship_1' => $student->emergency_contact_relationship_1,

                'high_school_name' => $student->high_school_name,
                'intake_semester' => $student->intakeSemester ? [
                    'id' => $student->intakeSemester->id,
                    'code' => $student->intakeSemester->code,
                    'name' => $student->intakeSemester->name,
                ] : null,
                'scholarship' => $student->scholarshipAward ? [
                    'code' => $student->scholarshipAward->scholarship_code,
                    'name' => $student->scholarshipAward->scholarshipDefinition?->name ?? $student->scholarshipAward->scholarship_code,
                    'amount' => $student->scholarshipAward->scholarshipDefinition?->amount,
                    'type' => $student->scholarshipAward->scholarshipDefinition?->type,
                    'awarded_at' => $student->scholarshipAward->awarded_at?->format('Y-m-d'),
                ] : null,
                'parent_user' => ($parentProfile = $student->parentProfiles->first()) && $parentProfile->user ? [
                    'id' => $parentProfile->user->id,
                    'name' => $parentProfile->user->name,
                    'email' => $parentProfile->user->email,
                ] : null,

            ],
            'academic_info' => [
                'intake_school' => $student->intakeSemester ? [
                    'code' => $student->intakeSemester->code,
                    'name' => $student->intakeSemester->name,
                    'intake_year' => Carbon::parse($student->intakeSemester->start_date)->year,
                ] : null,
                'intake_major' => $student->intakeMajorSemester ? [
                    'code' => $student->intakeMajorSemester->code,
                    'name' => $student->intakeMajorSemester->name,
                ] : null,
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
                    ->sum('credit_points_earned'),
                'total_credits_attempted' => $student->academicRecords()
                    ->sum('credit_points'),
                'current_gpa' => $currentGpa?->semester_gpa ?? 0,
                'cumulative_gpa' => $currentGpa?->cumulative_gpa ?? $currentGpa?->semester_gpa ?? 0,
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
            'recent_registrations' => $recentRegistrations,
        ];
    }

    /**
     * Get course registrations data with comprehensive filtering and pagination support
     *
     * @param  Student  $student  The student model
     * @param  array  $filters  Optional filters for semester, status, academic year
     * @param  int  $perPage  Number of items per page for pagination
     * @return array Course registrations data with filtering and pagination
     */
    private function getRegistrations(Student $student, array $filters = [], int $perPage = 50): array
    {
        $query = $student->courseRegistrations()
            ->with([
                'courseOffering.unit:id,name,code,credit_points',
                'courseOffering.semester:id,name,code,start_date,end_date',
                'semester:id,name,code,start_date,end_date',
            ])
            ->leftJoin('academic_records', function ($join) use ($student) {
                $join->on('course_registrations.course_offering_id', '=', 'academic_records.course_offering_id')
                    ->where('academic_records.student_id', '=', $student->id);
            })
            ->select(
                'course_registrations.*',
                'academic_records.final_percentage',
                'academic_records.final_letter_grade',
                'academic_records.grade_points as academic_grade_points',
                'academic_records.meets_attendance_requirement',
                'academic_records.grade_status',
                'academic_records.completion_status as academic_completion_status',
                'academic_records.is_passed as academic_is_passed',
                'academic_records.credit_points as academic_credit_points',
                'academic_records.credit_points_earned as academic_credit_points_earned',
                'academic_records.attempt_number as academic_attempt_number',
                'academic_records.is_repeat_course as academic_is_repeat_course',

            );

        // Apply filters. Columns are table-qualified because the academic_records
        // left join also carries a semester_id, which would otherwise be ambiguous.
        if (! empty($filters['semester_id'])) {
            $query->where('course_registrations.semester_id', $filters['semester_id']);
        }

        if (! empty($filters['academic_year'])) {
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
                            $q->where('code', 'LIKE', '%SPR'.$endYear.'%')
                                ->orWhere('name', 'LIKE', '%Spring '.$endYear.'%')
                                ->orWhere('name', 'LIKE', '%Spring'.$endYear.'%');
                        })->orWhere(function ($q) use ($startYear) {
                            // Fall semester of the academic year (contains start year)
                            $q->where('code', 'LIKE', '%FALL'.$startYear.'%')
                                ->orWhere('name', 'LIKE', '%Fall '.$startYear.'%')
                                ->orWhere('name', 'LIKE', '%Fall'.$startYear.'%');
                        });
                    }
                });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('course_registrations.registration_status', $filters['status']);
        }

        if (! empty($filters['is_retake'])) {
            $query->where('course_registrations.is_retake', $filters['is_retake'] === 'true');
        }

        // Apply sorting
        $sortField = $filters['sort'] ?? 'registration_date';
        $direction = $filters['direction'] ?? 'desc';

        $mappedSortField = match ($sortField) {
            'course_name' => 'units.name',
            'course_code' => 'units.code',
            'semester' => 'semesters.name',
            'registration_status' => 'course_registrations.registration_status',
            'final_grade' => 'academic_records.final_letter_grade',
            'pass_fail_status' => 'academic_records.is_passed',
            default => 'course_registrations.registration_date',
        };

        if (in_array($sortField, ['course_name', 'course_code'])) {
            $query->join('course_offerings as co_sort', 'course_registrations.course_offering_id', '=', 'co_sort.id')
                ->join('units', 'co_sort.unit_id', '=', 'units.id');
        } elseif ($sortField === 'semester') {
            $query->join('semesters', 'course_registrations.semester_id', '=', 'semesters.id');
        }

        $query->orderBy($mappedSortField, $direction);

        // Get all registrations for summary calculations (without pagination)
        $allRegistrations = $query->get();

        // Get paginated results
        $paginatedRegistrations = $query->paginate($perPage);

        // Transform the data
        $registrations = $paginatedRegistrations->getCollection()->map(function ($registration) {
            $semester = $registration->semester ?? $registration->courseOffering->semester;
            $unit = $registration->courseOffering->unit ?? null;

            // Use academic_record data if available, otherwise fall back to course_registration data
            $finalGrade = $registration->final_letter_grade ?? $registration->final_grade;
            $gradePoints = $registration->academic_grade_points ?? $registration->grade_points;
            $finalPercentage = $registration->final_percentage;
            $meetsAttendance = $registration->meets_attendance_requirement;
            $gradeStatus = $registration->grade_status;
            $completionStatus = $registration->academic_completion_status;

            // Determine Pass/Fail status based on academic_record
            $passFailStatus = null;
            if ($completionStatus === 'completed') {
                $passFailStatus = $registration->academic_is_passed ? 'pass' : 'fail';
            }

            return [
                'course_offering_id' => $registration->course_offering_id,
                'id' => $registration->id,
                'course_name' => $unit->name ?? 'N/A',
                'course_code' => $unit->code ?? 'N/A',
                'section_code' => $registration->courseOffering->section_code ?? 'N/A',
                'unit_credit_points' => $unit->credit_points ?? 0,
                'semester' => $semester->name ?? 'N/A',
                'semester_code' => $semester->code ?? 'N/A',
                'academic_year' => $this->getAcademicYear($semester),
                'semester_start_date' => $semester->start_date ?? null,
                'semester_end_date' => $semester->end_date ?? null,
                'registration_status' => $registration->registration_status,
                'registration_date' => $registration->registration_date,
                'registration_method' => $registration->registration_method ?? 'N/A',
                'meets_attendance_requirement' => $meetsAttendance,
                'grade_status' => $gradeStatus,

                // Information of academic record
                'final_grade' => $finalGrade, // for grade column
                'final_percentage' => $finalPercentage, // for grade column
                'credit_points' => $registration->academic_credit_points, // for credit points column
                'credit_points_earned' => $registration->academic_credit_points_earned,
                'completion_status' => $completionStatus,
                'pass_fail_status' => $passFailStatus,

                // Information of course retake
                'is_retake' => $registration->academic_is_repeat_course,
                'attempt_number' => $registration->academic_attempt_number,

                // Information of course registration
                'completion_date' => $registration->completion_date,
                'drop_date' => $registration->drop_date,

                'withdrawal_date' => $registration->withdrawal_date,
                'retake_fee' => $registration->retake_fee ?? 0,
                'is_retake_paid' => $registration->is_retake_paid ?? 'no',
                'notes' => $registration->notes,
                'status_badge_color' => $this->getCompletionStatusBadgeColor($completionStatus),
                'grade_badge_color' => $this->getGradeStatusBadgeColor($gradeStatus),
                'pass_fail_badge_color' => $this->getPassFailBadgeColor($passFailStatus),
                'is_passing_grade' => $this->isPassingGrade($finalGrade),
                'formatted_registration_date' => $registration->registration_date ?
                    Carbon::parse($registration->registration_date)->format('M j, Y') : null,
                'formatted_completion_date' => $registration->completion_date ?
                    Carbon::parse($registration->completion_date)->format('M j, Y') : null,
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
            'total_credits_attempted' => $allRegistrations->sum('credit_points'),
            'total_credits_earned' => $allRegistrations->where('registration_status', 'completed')
                ->where('final_grade', '!=', null)
                ->filter(function ($reg) {
                    return $this->isPassingGrade($reg->final_grade);
                })
                ->sum('credit_points'),
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
     * @param  string  $status  Registration status
     * @return string CSS color class
     */
    private function getRegistrationStatusBadgeColor(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'enrolled', 'active', 'registered', 'confirmed' => 'primary',
            'dropped' => 'warning',
            'withdrawn' => 'destructive',
            'defer' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Get badge color for Pass/Fail status
     *
     * @param  string|null  $status  Pass/Fail status
     * @return string CSS color class
     */
    private function getPassFailBadgeColor(?string $status): string
    {
        return match ($status) {
            'pass' => 'success',
            'fail' => 'destructive',
            default => 'secondary',
        };
    }

    /**
     * Get badge color for completion status
     *
     * @param  string|null  $status  Completion status
     * @return string CSS color class
     */
    private function getCompletionStatusBadgeColor(?string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'in_progress' => 'primary',
            'failed' => 'destructive',
            'withdrawn' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Get badge color for grade status
     *
     * @param  string|null  $status  Grade status
     * @return string CSS color class
     */
    private function getGradeStatusBadgeColor(?string $status): string
    {
        return match ($status) {
            'passing' => 'success',
            'failing' => 'destructive',
            default => 'secondary',
        };
    }

    /**
     * Check if grade is passing
     *
     * @param  string|null  $grade  Final grade
     * @return bool Whether grade is passing
     */
    private function isPassingGrade(?string $grade): bool
    {
        if (! $grade) {
            return false;
        }

        // Assuming D- and above are passing grades
        $passingGrades = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-'];

        return in_array(strtoupper($grade), $passingGrades);
    }

    /**
     * Derive academic year from semester data
     *
     * @param  object|null  $semester  Semester model instance
     * @return string Academic year (e.g., '2024-2025')
     */
    private function getAcademicYear($semester): string
    {
        if (! $semester) {
            return 'Unknown';
        }

        // Try to extract from semester code first (e.g., 'SPR2025' -> '2024-2025')
        if ($semester->code) {
            if (preg_match('/(\d{4})/', $semester->code, $matches)) {
                $year = (int) $matches[1];
                // If it's a spring semester, it's the second year of the academic year
                if (str_contains(strtolower($semester->code), 'spr')) {
                    return ($year - 1).'-'.$year;
                }

                // If it's a fall semester, it's the first year of the academic year
                return $year.'-'.($year + 1);
            }
        }

        // Try to extract from semester name (e.g., 'Spring 2025' -> '2024-2025')
        if ($semester->name) {
            if (preg_match('/(\d{4})/', $semester->name, $matches)) {
                $year = (int) $matches[1];
                // If it's a spring semester, it's the second year of the academic year
                if (str_contains(strtolower($semester->name), 'spring')) {
                    return ($year - 1).'-'.$year;
                }

                // If it's a fall semester, it's the first year of the academic year
                return $year.'-'.($year + 1);
            }
        }

        // Try to derive from start_date
        if ($semester->start_date) {
            $startYear = Carbon::parse($semester->start_date)->year;
            $startMonth = Carbon::parse($semester->start_date)->month;

            // If semester starts in Jan-Jul, it's likely the second year of the academic year
            if ($startMonth <= 7) {
                return ($startYear - 1).'-'.$startYear;
            }

            // If semester starts in Aug-Dec, it's likely the first year of the academic year
            return $startYear.'-'.($startYear + 1);
        }

        return 'Unknown';
    }

    /**
     * Get assessment scores data grouped by course offering with pagination support
     *
     * @param  Student  $student  The student model
     * @param  int  $limit  Limit for recent scores per course (default: 5)
     * @return array Assessment scores data
     */
    private function getScores(Student $student, int $limit = 100): array
    {
        $scores = AssessmentComponentDetailScore::where('student_id', $student->id)
            ->with([
                'courseOffering.unit:id,name,code',
                'courseOffering.semester:id,name,code',
                'assessmentComponentDetail.assessmentComponent:id,name,type,weight',
                'assessmentComponentDetail:id,assessment_component_id,name,description,due_date,max_points',
            ])
            ->orderBy('graded_at', 'desc')
            ->get()
            ->groupBy('course_offering_id')
            ->map(function ($courseScores, $courseOfferingId) use ($student, $limit) {
                $firstScore = $courseScores->first();

                // Get final_percentage from academic_records table
                $academicRecord = $student->academicRecords()
                    ->where('course_offering_id', $courseOfferingId)
                    ->first();

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
                    'course_name' => $firstScore->courseOffering->unit->name ?? 'N/A',
                    'course_code' => $firstScore->courseOffering->unit->code ?? 'N/A',
                    'semester' => $firstScore->courseOffering->semester->name ?? 'N/A',
                    'scores' => $allScores,
                    'all_scores_count' => $allScores->count(),
                    'has_more_scores' => $allScores->count() > $limit,
                    'course_average' => $academicRecord?->final_percentage ?? 0,
                    'credit_points' => $academicRecord ? (float) $academicRecord->credit_points : 0.0,
                    'credit_points_earned' => $academicRecord ? (float) $academicRecord->credit_points_earned : 0.0,
                    'is_passed' => $academicRecord ? (bool) $academicRecord->is_passed : null,
                    'final_letter_grade' => $academicRecord?->final_letter_grade,
                    'grade_status' => $academicRecord?->grade_status,
                    'total_assessments' => $courseScores->count(),
                    'completed_assessments' => $courseScores->where('status', 'graded')->count(),
                ];
            })
            ->values();

        // Get module scores
        $moduleScores = $this->getModuleScores($student);
        $creditPointSnapshots = $this->getCreditPointSnapshots($student);

        // Per-semester GPA snapshots + cumulative (sources of truth for the transcript view).
        $gpaRows = GpaCalculation::where('student_id', $student->id)
            ->with(['semester:id,name,code,start_date'])
            ->orderBy('semester_id', 'asc')
            ->get();

        $semesterGpa = $gpaRows->map(function (GpaCalculation $g) use ($creditPointSnapshots): array {
            $semesterCredits = $creditPointSnapshots['semesters']->get($g->semester_id);

            return [
                'semester_id' => $g->semester_id,
                'semester_name' => $g->semester?->name ?? 'N/A',
                'semester_code' => $g->semester?->code ?? '',
                'start_date' => optional($g->semester?->start_date)->toDateString(),
                'semester_gpa' => (float) $g->semester_gpa,
                'credit_points_attempted' => $semesterCredits['attempted'] ?? (float) $g->semester_credit_points,
                'credit_points_earned' => $semesterCredits['earned'] ?? (float) $g->semester_credit_points_earned,
                'academic_standing' => $g->academic_standing,
                'is_finalized' => (bool) $g->is_finalized,
                'finalized_at' => optional($g->finalized_at)->toIso8601String(),
            ];
        })->values();

        $currentGpa = $gpaRows->firstWhere('is_current', true) ?? $gpaRows->last();
        $cumulativeCredits = $creditPointSnapshots['cumulative'];

        $cumulative = $currentGpa ? [
            'gpa' => (float) $currentGpa->cumulative_gpa,
            'credit_points_attempted' => $cumulativeCredits['has_records']
                ? $cumulativeCredits['attempted']
                : (float) $currentGpa->cumulative_credit_points,
            'credit_points_earned' => $cumulativeCredits['has_records']
                ? $cumulativeCredits['earned']
                : (float) $currentGpa->cumulative_credit_points_earned,
            'academic_standing' => $currentGpa->academic_standing,
            'last_finalized_at' => optional($currentGpa->finalized_at)->toIso8601String(),
            'semesters_count' => $gpaRows->count(),
        ] : null;

        return [
            'standalone_units' => [
                'data' => $scores,
                'summary' => [
                    'total_courses' => $scores->count(),
                    'total_assessments' => $scores->sum('total_assessments'),
                    'completed_assessments' => $scores->sum('completed_assessments'),
                    'overall_average' => $scores->avg('course_average'),
                ],
            ],
            'modules' => $moduleScores->isNotEmpty() ? [
                'data' => $moduleScores,
                'summary' => [
                    'total_modules' => $moduleScores->count(),
                    'completed_modules' => $moduleScores->where('status', 'passed')->count(),
                    'in_progress_modules' => $moduleScores->where('status', 'in_progress')->count(),
                    'failed_modules' => $moduleScores->where('status', 'failed')->count(),
                    'average_grade' => $moduleScores->where('module_grade', '!=', null)->avg('module_grade'),
                ],
            ] : null,
            'semesters' => $semesterGpa,
            'cumulative' => $cumulative,
            'summary' => [
                'total_courses' => $scores->count(),
                'total_modules' => $moduleScores->count(),
                'total_assessments' => $scores->sum('total_assessments'),
                'completed_assessments' => $scores->sum('completed_assessments'),
                'overall_average' => $scores->avg('course_average'),
            ],
        ];
    }

    /**
     * Build credit attempted/earned snapshots from academic records.
     *
     * Attempted credits count every final credit-bearing attempt, including
     * retakes. Earned credits count only attempts marked as passed.
     */
    private function getCreditPointSnapshots(Student $student): array
    {
        $records = $student->academicRecords()
            ->where('excluded_from_gpa', false)
            ->where('grade_status', 'final')
            ->where('credit_points', '>', 0)
            ->get(['semester_id', 'credit_points', 'is_passed']);

        $semesters = $records
            ->groupBy('semester_id')
            ->map(fn ($semesterRecords): array => [
                'attempted' => (float) $semesterRecords->sum('credit_points'),
                'earned' => (float) $semesterRecords->where('is_passed', true)->sum('credit_points'),
            ]);

        return [
            'semesters' => $semesters,
            'cumulative' => [
                'attempted' => (float) $records->sum('credit_points'),
                'earned' => (float) $records->where('is_passed', true)->sum('credit_points'),
                'has_records' => $records->isNotEmpty(),
            ],
        ];
    }

    /**
     * Get module scores with sub-unit details
     *
     * @param  Student  $student  The student model
     * @return Collection Module scores data
     */
    private function getModuleScores(Student $student): Collection
    {
        // Check if student has curriculum with modules
        if (! $student->curriculumVersion) {
            return collect([]);
        }

        // Get modules from student's curriculum
        $curriculumModules = $student->curriculumVersion
            ->curriculumModules()
            ->with([
                'module:id,code,name,total_credits,grading_type',
                'module.units:id,code,name,credit_points',
            ])
            ->get();

        if ($curriculumModules->isEmpty()) {
            return collect([]);
        }

        // Calculate progress for each module
        return $curriculumModules->map(function ($curriculumModule) use ($student) {
            $module = $curriculumModule->module;

            // Get sub-unit IDs
            $unitIds = $module->units->pluck('id')->toArray();

            // Get academic records for sub-units
            $academicRecords = $student->academicRecords()
                ->whereHas('courseOffering', function ($q) use ($unitIds) {
                    $q->whereIn('unit_id', $unitIds);
                })
                ->with(['courseOffering:id,unit_id,grading_type', 'unit:id,code,name,credit_points'])
                ->get();

            // Separate graded vs pass/fail units
            $gradedRecords = $academicRecords->filter(
                fn ($r) => $r->courseOffering && $r->courseOffering->grading_type === 'grade'
            );
            $passfailRecords = $academicRecords->filter(
                fn ($r) => $r->courseOffering && $r->courseOffering->grading_type === 'pass_fail'
            );

            // Calculate module grade (only from graded units)
            $moduleGrade = null;
            $usesWeights = false;

            if ($gradedRecords->isNotEmpty()) {
                // Check if weights are defined
                $firstUnit = $module->units->first();
                $usesWeights = $firstUnit && $firstUnit->pivot->weight !== null;

                if ($usesWeights) {
                    // Weighted average
                    $totalWeight = 0;
                    $weightedSum = 0;

                    foreach ($gradedRecords as $record) {
                        $unit = $module->units->find($record->unit_id);
                        if ($unit && $unit->pivot->weight) {
                            $weight = $unit->pivot->weight;
                            $totalWeight += $weight;
                            $weightedSum += ($record->final_percentage ?? 0) * $weight;
                        }
                    }

                    if ($totalWeight > 0) {
                        $moduleGrade = $weightedSum / $totalWeight;
                    }
                } else {
                    // Simple average
                    $moduleGrade = $gradedRecords->avg('final_percentage');
                }
            }

            // Determine module status
            $totalUnits = $module->units->count();
            $completedRecords = $academicRecords->where('completion_status', 'completed');
            $completedUnits = $completedRecords->count();

            $status = 'in_progress';
            if ($completedUnits === $totalUnits && $totalUnits > 0) {
                // All units completed - check if all passed
                $allPassed = $completedRecords->every(function ($record) {
                    return $record->final_letter_grade &&
                        ! in_array(strtoupper($record->final_letter_grade), ['F', 'FAIL']);
                });
                $status = $allPassed ? 'passed' : 'failed';
            } elseif ($completedUnits > 0) {
                $status = 'in_progress';
            } else {
                $status = 'not_started';
            }

            // Map sub-unit details
            $subUnits = $module->units->map(function ($unit) use ($academicRecords) {
                $record = $academicRecords->firstWhere('unit_id', $unit->id);
                $gradingType = $record?->courseOffering?->grading_type ?? 'grade';

                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credits' => $unit->credit_points,
                    'grading_type' => $gradingType,
                    'weight' => $unit->pivot->weight,
                    'order' => $unit->pivot->order ?? 0,
                    'final_grade' => $record?->final_percentage,
                    'letter_grade' => $record?->final_letter_grade,
                    'status' => $record?->completion_status ?? 'not_enrolled',
                    'is_passed' => $record && $record->final_letter_grade &&
                        ! in_array(strtoupper($record->final_letter_grade), ['F', 'FAIL']),
                    'included_in_average' => $gradingType === 'grade',
                ];
            })->sortBy('order')->values();

            return [
                'module_id' => $module->id,
                'module_code' => $module->code,
                'module_name' => $module->name,
                'module_grade' => $moduleGrade,
                'total_credits' => $module->total_credits,
                'year_level' => $curriculumModule->year_level,
                'semester_number' => $curriculumModule->semester_number,
                'is_required' => $curriculumModule->is_required,
                'group_name' => $curriculumModule->group_name,
                'status' => $status,
                'completion' => [
                    'completed' => $completedUnits,
                    'total' => $totalUnits,
                    'percentage' => ($totalUnits > 0) ? round(($completedUnits / $totalUnits) * 100) : 0,
                ],
                'grading_info' => [
                    'type' => $module->grading_type,
                    'graded_units_count' => $gradedRecords->count(),
                    'passfail_units_count' => $passfailRecords->count(),
                    'uses_weights' => $usesWeights,
                ],
                'sub_units' => $subUnits,
            ];
        });
    }

    /**
     * Get student overview data.
     *
     * Academic Affairs (act-capable) staff receive the full overview. Other
     * roles with only `view_student_summary` receive a reduced read-only field
     * set (ADR-0007: reduced fields for non-owning roles).
     *
     * @param  Student  $student  The student model
     * @param  bool  $full  Whether to return the full overview or the reduced read-only set
     * @return array Overview data
     */
    public function getOverviewData(Student $student, bool $full = true): array
    {
        $overview = $this->getStudentOverview($student);

        return $full ? $overview : $this->reduceOverviewForReadOnly($overview);
    }

    /**
     * Reduce the overview to a read-only field set for non act-capable roles.
     *
     * Drops personal PII (national id, addresses, emergency contacts, parent
     * login, etc.) and the folded operational blocks (additional info, recent
     * registrations); keeps identity, program context, and aggregate stats.
     *
     * @param  array<string, mixed>  $overview
     * @return array<string, mixed>
     */
    private function reduceOverviewForReadOnly(array $overview): array
    {
        $keepStudentInfo = [
            'id',
            'student_id',
            'full_name',
            'email',
            'status',
            'academic_status',
            'avatar_url',
            'admission_date',
            'expected_graduation_date',
            'intake_semester',
        ];

        return [
            'student_info' => array_intersect_key(
                $overview['student_info'],
                array_flip($keepStudentInfo)
            ),
            'academic_info' => $overview['academic_info'],
            'program_info' => $overview['program_info'],
            'academic_stats' => $overview['academic_stats'],
        ];
    }

    /**
     * Get registrations data
     *
     * @param  Student  $student  The student model
     * @param  array  $filters  Optional filters
     * @param  int  $perPage  Items per page
     * @return array Registrations data
     */
    public function getRegistrationsData(Student $student, array $filters = [], int $perPage = 50): array
    {
        return $this->getRegistrations($student, $filters, $perPage);
    }

    /**
     * Get scores data
     *
     * @param  Student  $student  The student model
     * @param  int  $limit  Score limit
     * @return array Scores data
     */
    public function getScoresData(Student $student, int $limit = 100): array
    {
        return $this->getScores($student, $limit);
    }

    /**
     * Get attendance data
     *
     * @param  Student  $student  The student model
     * @return array Attendance data
     */
    public function getAttendanceData(Student $student): array
    {
        return $this->getAttendance($student);
    }

    /**
     * Get graduation data
     *
     * @param  Student  $student  The student model
     * @return array Graduation data
     */
    public function getGraduationData(Student $student): array
    {
        return $this->getGraduationTracker($student);
    }

    /**
     * Get detailed scores for a specific course offering (for lazy loading)
     *
     * @param  Student  $student  The student model
     * @param  int  $courseOfferingId  The course offering ID
     * @param  int  $offset  Offset for pagination
     * @param  int  $limit  Limit for pagination
     * @return array Detailed scores data
     */
    public function getCourseScoresDetails(Student $student, int $courseOfferingId, int $offset = 0, int $limit = 20): array
    {
        $scores = AssessmentComponentDetailScore::where('student_id', $student->id)
            ->where('course_offering_id', $courseOfferingId)
            ->with([
                'courseOffering.unit:id,name,code',
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
     * @param  Student  $student  The student model
     * @return array Attendance summary data
     */
    private function getAttendance(Student $student): array
    {
        // Get attendance records with related data
        $attendanceData = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
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
     * @param  float  $percentage  Attendance percentage
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
     * Get graduation progress tracking data
     *
     * @param  Student  $student  The student model
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
        $totalCreditsEarned = $completedRecords->sum('credit_points_earned');
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
                )->sum('credit_points_earned'),
                'status' => 'in_progress', // Would calculate based on actual completion
            ],
            'elective_credits' => [
                'required' => $curriculumUnits->where('type', 'elective')->sum(function ($curriculumUnit) {
                    return $curriculumUnit->unit ? $curriculumUnit->unit->credit_points : 0;
                }),
                'earned' => $completedRecords->whereIn(
                    'unit_id',
                    $curriculumUnits->where('type', 'elective')->pluck('unit_id')
                )->sum('credit_points_earned'),
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
        if (! $requirements['internship']['completed']) {
            $risks[] = 'internship_pending';
        }
        if (! $requirements['thesis']['completed']) {
            $risks[] = 'thesis_pending';
        }
        if (! $requirements['english_requirement']['completed']) {
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
     * @param  Student  $student  The student model
     * @return array Current semester progress data
     */
    private function getCurrentSemesterProgress(Student $student): array
    {
        $currentSemester = Semester::where('is_active', true)->first();

        if (! $currentSemester) {
            return ['semester' => null, 'enrolled_credits' => 0, 'status' => 'no_current_semester'];
        }

        $currentRegistrations = $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->whereIn('registration_status', ['enrolled', 'active'])
            ->sum('credit_points');

        return [
            'semester' => $currentSemester->name,
            'enrolled_credits' => $currentRegistrations,
            'status' => $currentRegistrations > 0 ? 'enrolled' : 'not_enrolled',
        ];
    }

    /**
     * Calculate projected completion date
     *
     * @param  Student  $student  The student model
     * @param  float  $creditsRemaining  Remaining credits to complete
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
                Carbon::parse($student->expected_graduation_date) :
                now()->addYears(2)),
        ];
    }

    /**
     * Get detailed attendance information for a specific unit
     *
     * @param  int  $studentId  The student ID
     * @param  int  $unitId  The unit ID
     * @param  int|null  $semesterId  Optional semester filter
     * @return array Detailed attendance data
     */
    public function getAttendanceDetails(int $studentId, int $unitId, ?int $semesterId = null): array
    {
        $query = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
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
     * @param  int  $studentId  The student ID
     * @param  int  $courseOfferingId  The course offering ID
     * @return array Detailed score breakdown
     */
    public function getScoreDetails(int $studentId, int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::with(['unit:id,name,code', 'semester:id,name,code'])
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
                'name' => $courseOffering->unit->name ?? 'N/A',
                'code' => $courseOffering->unit->code ?? 'N/A',
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
     * @param  Collection  $components
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
