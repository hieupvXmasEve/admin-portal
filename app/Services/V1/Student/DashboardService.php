<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\AcademicHold;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Enrollment;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        protected GPACalculationService $gpaService,
        protected CreditProgressService $creditProgressService
    ) {}

    /**
     * Get complete dashboard data for student
     */
    public function getDashboardData(Student $student): array
    {
        $cacheKey = "dashboard:student:{$student->id}";

        return Cache::remember($cacheKey, 300, function () use ($student) {
            return [
                'current_semester' => $this->getCurrentSemesterData($student),
                'gpa_data' => $this->getGPAData($student),
                'credit_progress' => $this->getCreditProgress($student),
                'academic_holds' => $this->getAcademicHolds($student),
                'upcoming_assessments' => $this->getUpcomingAssessments($student),
                'enrollment_status' => $this->getEnrollmentStatus($student),
                'quick_stats' => $this->getQuickStats($student),
            ];
        });
    }

    /**
     * Get current semester information
     */
    public function getCurrentSemesterData(Student $student): array
    {
        $currentSemester = $this->currentSemester();

        if (! $currentSemester) {
            return [
                'semester' => null,
                'enrollment' => null,
                'registered_courses' => 0,
                'total_credits' => 0,
            ];
        }
        // $enrollment = $student->enrollments()
        //     ->where('semester_id', $currentSemester->id)
        //     ->first();

        $registrations = $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->with(['courseOffering.unit'])
            ->get();

        return [
            'semester' => [
                'id' => $currentSemester->id,
                'name' => $currentSemester->name,
                'code' => $currentSemester->code,
                'start_date' => $currentSemester->start_date->toDateString(),
                'end_date' => $currentSemester->end_date->toDateString(),
                'is_registration_open' => $currentSemester->isRegistrationOpen(),
            ],
            // 'enrollment' => $enrollment ? [
            //     'id' => $enrollment->id,
            //     'status' => $enrollment->status,
            //     'semester_number' => $enrollment->semester_number,
            // ] : null,
            'registered_courses' => $registrations->count(),
            'total_credits' => $registrations->sum('credit_points'),
            'courses' => $registrations->map(function ($registration) {
                return [
                    'id' => $registration->id,
                    'course_code' => $registration->courseOffering->unit->code,
                    'course_name' => $registration->courseOffering->unit->name,
                    'credits' => $registration->credit_points,
                    'status' => $registration->registration_status,
                ];
            }),
        ];
    }

    /**
     * Get GPA data for student
     */
    public function getGPAData(Student $student): array
    {
        $currentGPA = $this->gpaService->calculateCurrentGPA($student);
        $gpaTrend = $this->gpaService->getGPATrend($student);
        $gradeDistribution = $this->gpaService->getGradeDistribution($student);
        $academicStanding = $this->gpaService->getAcademicStanding($student);

        return [
            'current_gpa' => $currentGPA,
            'gpa_trend' => $gpaTrend,
            'grade_distribution' => $gradeDistribution,
            'academic_standing' => $academicStanding,
        ];
    }

    /**
     * Get credit progress information
     */
    public function getCreditProgress(Student $student): array
    {
        return $this->creditProgressService->getCreditProgress($student);
    }

    /**
     * Get academic holds
     */
    public function getAcademicHolds(Student $student): array
    {
        $holds = AcademicHold::where('student_id', $student->id)
            ->where('status', 'active')
            ->orderBy('priority', 'desc')
            ->orderBy('placed_date', 'desc')
            ->get();

        return [
            'total_holds' => $holds->count(),
            'blocking_registration' => $holds->where('hold_category', 'registration')->count(),
            'blocking_graduation' => $holds->where('hold_category', 'graduation')->count(),
            'holds' => $holds->map(function ($hold) {
                return [
                    'id' => $hold->id,
                    'type' => $hold->hold_type,
                    'category' => $hold->hold_category,
                    'title' => $hold->title,
                    'description' => $hold->description,
                    'amount' => $hold->amount,
                    'priority' => $hold->priority,
                    'placed_date' => $hold->placed_date->toDateString(),
                    'due_date' => $hold->due_date?->toDateString(),
                ];
            }),
        ];
    }

    /**
     * Get upcoming assessments
     */
    public function getUpcomingAssessments(Student $student): array
    {
        $currentSemester = $this->currentSemester();

        if (! $currentSemester) {
            return ['assessments' => []];
        }

        // Get upcoming assessments from registered courses
        $upcomingAssessments = AssessmentComponentDetailScore::whereHas('courseOffering', function ($query) use ($student, $currentSemester) {
            $query->where('semester_id', $currentSemester->id)
                ->whereHas('courseRegistrations', function ($regQuery) use ($student) {
                    $regQuery->where('student_id', $student->id)
                        ->where('registration_status', 'registered');
                });
        })
            ->where('student_id', $student->id)
            ->whereNull('submitted_at')
            ->with(['assessmentComponentDetail.assessmentComponent', 'courseOffering.unit'])
            ->join('assessment_component_details', 'assessment_component_detail_scores.assessment_component_detail_id', '=', 'assessment_component_details.id')
            ->join('assessment_components', 'assessment_component_details.assessment_component_id', '=', 'assessment_components.id')
            ->orderBy('assessment_components.due_date')
            ->select('assessment_component_detail_scores.*')
            ->limit(10)
            ->get();

        return [
            'total_upcoming' => $upcomingAssessments->count(),
            'assessments' => $upcomingAssessments->map(function ($assessment) {
                return [
                    'id' => $assessment->id,
                    'title' => $assessment->assessmentComponentDetail->name,
                    'type' => $assessment->assessmentComponentDetail->assessmentComponent->type,
                    'course_code' => $assessment->courseOffering->unit->code,
                    'course_name' => $assessment->courseOffering->unit->name,
                    'due_date' => $assessment->assessmentComponentDetail->assessmentComponent->due_date?->toDateString(),
                    'max_score' => $assessment->max_score,
                    'weight' => $assessment->assessmentComponentDetail->weight,
                    'status' => $assessment->submission_status,
                ];
            }),
        ];
    }

    /**
     * Get enrollment status
     */
    protected function getEnrollmentStatus(Student $student): array
    {
        $currentSemester = $this->currentSemester();

        if (! $currentSemester) {
            return ['status' => 'no_active_semester'];
        }

        $enrollment = $student->enrollments()
            ->where('semester_id', $currentSemester->id)
            ->first();

        return [
            'status' => $enrollment?->status ?? 'not_enrolled',
            'semester_number' => $enrollment?->semester_number,
        ];
    }

    /**
     * Get quick statistics
     */
    protected function getQuickStats(Student $student): array
    {
        return [
            'total_courses_completed' => $student->academicRecords()
                ->where('completion_status', 'completed')
                ->count(),
            'current_semester_courses' => $student->courseRegistrations()
                ->whereHas('semester', fn ($q) => $q->where('is_active', true))
                ->where('registration_status', 'registered')
                ->count(),
            'attendance_rate' => $this->calculateOverallAttendanceRate($student),
        ];
    }

    private function currentSemester(): ?Semester
    {
        $currentPeriodId = app(AcademicPeriodReader::class)->current()?->id;

        return $currentPeriodId === null ? null : Semester::find($currentPeriodId);
    }

    /**
     * Check if student is on track for graduation
     */
    protected function isOnTrackForGraduation(Student $student): bool
    {
        // Simple logic - can be enhanced
        $latestGPA = GpaCalculation::where('student_id', $student->id)
            ->current()
            ->orderBy('created_at', 'desc')
            ->first();

        return $latestGPA && $latestGPA->on_track_to_graduate;
    }

    /**
     * Get projected graduation date
     */
    protected function getProjectedGraduation(Student $student): ?string
    {
        $latestGPA = GpaCalculation::where('student_id', $student->id)
            ->current()
            ->orderBy('created_at', 'desc')
            ->first();

        return $latestGPA?->projected_graduation_date?->toDateString();
    }

    /**
     * Calculate overall attendance rate
     */
    protected function calculateOverallAttendanceRate(Student $student): float
    {
        $totalSessions = $student->attendances()->count();

        if ($totalSessions === 0) {
            return 0.0;
        }

        $presentSessions = $student->attendances()
            ->whereIn('status', ['present', 'late'])
            ->count();

        return round(($presentSessions / $totalSessions) * 100, 1);
    }
}
