<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\AssessmentComponentDetailScore;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Support\Grading\Presenters\GradeDisplayPresenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class GradeService
{
    public function __construct(
        private readonly GradeDisplayPresenter $gradeDisplayPresenter,
    ) {}

    /**
     * Get student's grades for a specific semester or all semesters
     */
    public function getStudentGrades(Student $student, ?int $semesterId = null, array $filters = []): array
    {
        // $cacheKey = "grades:student:{$student->id}:semester:".($semesterId ?? 'all').':'.md5(serialize($filters));

        // return Cache::remember($cacheKey, 600, function () use ($student, $semesterId, $filters) {
        $query = $student->academicRecords()->with([
            'unit',
            'semester',
            'courseOffering.lecture',
        ]);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        // Apply filters
        $this->applyGradeFilters($query, $filters);

        $academicRecords = $query->orderBy('semester_id', 'desc')
            ->orderBy('completion_date', 'desc')
            ->get();

        // Get curriculum unit IDs
        $curriculumUnitIds = [];

        if ($student->curriculumVersion) {
            $curriculumUnitIds = $student->curriculumVersion
                ->curriculumUnits()
                ->pluck('unit_id')
                ->toArray();
        }

        // Separate curriculum vs EGC records
        $curriculumRecords = $academicRecords->filter(
            fn ($r) => in_array($r->unit_id, $curriculumUnitIds)
        );

        // EGC = only units with unit_type = 'egc'
        $egcRecords = $academicRecords->filter(
            fn ($r) => $r->unit->unit_type === 'egc'
        );

        $gradesBySemester = $this->buildGradesBySemester($student, $curriculumRecords);

        return [
            'grades_by_semester' => $gradesBySemester,
            'egc_units' => $egcRecords->isNotEmpty() ? $this->buildEGCUnits($egcRecords) : [],
            'overall_summary' => $this->buildSimpleSummary($student, $gradesBySemester, $egcRecords),
        ];
        // });
    }

    /**
     * Get GPA trend analysis
     */
    public function getGPATrend(Student $student, int $semesterCount = 8): array
    {
        $gpaCalculations = GpaCalculation::where('student_id', $student->id)
            ->where('calculation_type', 'semester')
            ->with('semester')
            ->orderBy('created_at', 'desc')
            ->limit($semesterCount)
            ->get()
            ->reverse()
            ->values();

        if ($gpaCalculations->isEmpty()) {
            return [
                'trend_data' => [],
                'trend_analysis' => [
                    'direction' => 'no_data',
                    'consistency' => 'no_data',
                    'improvement_rate' => 0,
                ],
                'predictions' => [],
            ];
        }

        return [
            'trend_data' => $this->formatGPATrendData($gpaCalculations),
            'trend_analysis' => $this->analyzeGPATrend($gpaCalculations),
            'predictions' => $this->predictFutureGPA($gpaCalculations),
        ];
    }

    /**
     * Get grades for a specific course
     */
    public function getCourseGrades(Student $student, int $courseOfferingId): array
    {
        $academicRecord = $student->academicRecords()
            ->where('course_offering_id', $courseOfferingId)
            ->with(['unit', 'semester', 'courseOffering.lecture'])
            ->first();

        if (! $academicRecord) {
            throw new \Exception('No academic record found for this course');
        }

        $assessmentScores = AssessmentComponentDetailScore::where('student_id', $student->id)
            ->where('course_offering_id', $courseOfferingId)
            ->with([
                'assessmentComponentDetail.assessmentComponent',
                'assessmentComponentDetail.assessmentComponent.assessmentType',
            ])
            ->orderBy('due_date')
            ->get();

        return [
            'course_info' => [
                'code' => $academicRecord->unit->code,
                'name' => $academicRecord->unit->name,
                'credit_hours' => $academicRecord->credit_hours,
                'semester' => $academicRecord->semester->name,
                'lecturer' => $academicRecord->courseOffering->lecture?->full_name ?? null,
            ],
            'final_grade' => [
                'letter_grade' => $academicRecord->final_letter_grade,
                'numeric_grade' => $academicRecord->final_numeric_grade,
                'grade_points' => $academicRecord->grade_points,
                'quality_points' => $academicRecord->quality_points,
                'completion_status' => $academicRecord->completion_status,
                'completion_date' => $academicRecord->completion_date?->toDateString(),
                'grade_display' => $this->gradeDisplayPresenter->present($academicRecord),
            ],
            'assessment_breakdown' => $this->formatAssessmentBreakdown($assessmentScores),
            'grade_calculation' => $this->calculateGradeBreakdown($assessmentScores),
            'performance_analysis' => $this->analyzeCoursePerformance($assessmentScores),
        ];
    }

    /**
     * Get assessment details
     */
    public function getAssessments(Student $student, array $filters = []): array
    {
        $query = AssessmentComponentDetailScore::where('student_id', $student->id)
            ->with([
                'assessmentComponentDetail.assessmentComponent.assessmentType',
                'courseOffering.unit',
                'courseOffering.semester',
            ]);

        // Apply filters
        $this->applyAssessmentFilters($query, $filters);

        $assessments = $query->orderBy('due_date', 'desc')->get();

        return [
            'assessments' => $this->formatAssessments($assessments),
            'summary' => $this->calculateAssessmentSummary($assessments),
            'upcoming' => $this->getUpcomingAssessments($student),
            'performance_by_type' => $this->analyzePerformanceByAssessmentType($assessments),
        ];
    }

    /**
     * Get specific assessment detail
     */
    public function getAssessmentDetail(Student $student, int $assessmentId): array
    {
        $assessment = AssessmentComponentDetailScore::where('student_id', $student->id)
            ->where('id', $assessmentId)
            ->with([
                'assessmentComponentDetail.assessmentComponent.assessmentType',
                'courseOffering.unit',
                'courseOffering.lecture',
            ])
            ->firstOrFail();

        return [
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->assessmentComponentDetail->name,
                'description' => $assessment->assessmentComponentDetail->description,
                'type' => $assessment->assessmentComponentDetail->assessmentComponent->type,
                'weight' => $assessment->assessmentComponentDetail->weight,
                'max_score' => $assessment->max_score,
                'due_date' => $assessment->due_date?->toDateString(),
                'submission_date' => $assessment->submitted_at?->toDateString(),
                'status' => $assessment->submission_status,
            ],
            'course' => [
                'code' => $assessment->courseOffering->unit->code,
                'name' => $assessment->courseOffering->unit->name,
                'lecturer' => $assessment->courseOffering->lecture?->full_name ?? null,
            ],
            'score' => [
                'achieved_score' => $assessment->achieved_score,
                'max_score' => $assessment->max_score,
                'percentage' => $assessment->max_score > 0
                    ? round(($assessment->achieved_score / $assessment->max_score) * 100, 1)
                    : 0,
                'grade_equivalent' => $this->calculateGradeEquivalent($assessment->achieved_score, $assessment->max_score),
            ],
            'feedback' => [
                'comments' => $assessment->feedback_comments,
                'graded_date' => $assessment->graded_at?->toDateString(),
                'graded_by' => $assessment->graded_by,
            ],
        ];
    }

    /**
     * Group grades by semester
     */
    protected function groupGradesBySemester(Collection $academicRecords): array
    {
        return $academicRecords->groupBy('semester_id')->map(function ($records, $semesterId) {
            $semester = $records->first()->semester;

            return [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                ],
                'courses' => $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'unit_code' => $record->unit->code,
                        'unit_name' => $record->unit->name,
                        'credit_hours' => $record->credit_hours,
                        'final_grade' => $record->final_letter_grade,
                        'numeric_grade' => $record->final_numeric_grade,
                        'grade_points' => $record->grade_points,
                        'completion_status' => $record->completion_status,
                        'completion_date' => $record->completion_date?->toDateString(),
                        'lecturer' => $record->courseOffering->lecture?->full_name ?? null,
                    ];
                })->values(),
                'semester_summary' => [
                    'total_courses' => $records->count(),
                    'completed_courses' => $records->where('completion_status', 'completed')->count(),
                    'total_credits' => $records->sum('credit_hours'),
                    'earned_credits' => $records->where('completion_status', 'completed')->sum('credit_hours_earned'),
                    'semester_gpa' => $this->calculateSemesterGPA($records),
                ],
            ];
        })->values()->toArray();
    }

    /**
     * Calculate overall summary
     */
    protected function calculateOverallSummary(Collection $academicRecords): array
    {
        $completedRecords = $academicRecords->where('completion_status', 'completed');

        return [
            'total_courses_attempted' => $academicRecords->count(),
            'total_courses_completed' => $completedRecords->count(),
            'total_credits_attempted' => $academicRecords->sum('credit_hours'),
            'total_credits_earned' => $completedRecords->sum('credit_hours_earned'),
            'overall_gpa' => $this->calculateOverallGPA($completedRecords),
            'completion_rate' => $academicRecords->count() > 0
                ? round(($completedRecords->count() / $academicRecords->count()) * 100, 1)
                : 0,
        ];
    }

    /**
     * Calculate grade distribution
     */
    protected function calculateGradeDistribution(Collection $academicRecords): array
    {
        $completedRecords = $academicRecords->where('completion_status', 'completed');
        $distribution = $completedRecords->groupBy('final_letter_grade')->map->count();

        $standardGrades = ['HD', 'D', 'C', 'P', 'N', 'F'];
        $formattedDistribution = [];

        foreach ($standardGrades as $grade) {
            $count = $distribution[$grade] ?? 0;
            $formattedDistribution[] = [
                'grade' => $grade,
                'count' => $count,
                'percentage' => $completedRecords->count() > 0
                    ? round(($count / $completedRecords->count()) * 100, 1)
                    : 0,
            ];
        }

        return $formattedDistribution;
    }

    /**
     * Calculate performance trends
     */
    protected function calculatePerformanceTrends(Student $student): array
    {
        $recentGPAs = GpaCalculation::where('student_id', $student->id)
            ->where('calculation_type', 'semester')
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->pluck('gpa')
            ->reverse()
            ->values();

        if ($recentGPAs->count() < 2) {
            return [
                'trend' => 'insufficient_data',
                'direction' => 'unknown',
                'improvement_rate' => 0,
                'consistency' => 'insufficient_data',
            ];
        }

        $latest = $recentGPAs->last();
        $previous = $recentGPAs->get($recentGPAs->count() - 2);
        $change = $latest - $previous;

        return [
            'trend' => $change > 0.1 ? 'improving' : ($change < -0.1 ? 'declining' : 'stable'),
            'direction' => $change > 0 ? 'upward' : ($change < 0 ? 'downward' : 'stable'),
            'improvement_rate' => round($change, 2),
            'consistency' => $this->calculateConsistency($recentGPAs),
        ];
    }

    /**
     * Apply grade filters
     */
    protected function applyGradeFilters($query, array $filters): void
    {
        if (! empty($filters['completion_status'])) {
            $query->where('completion_status', $filters['completion_status']);
        }

        if (! empty($filters['grade'])) {
            $query->where('final_letter_grade', $filters['grade']);
        }

        if (! empty($filters['unit_code'])) {
            $query->whereHas('unit', function ($q) use ($filters) {
                $q->where('code', 'like', '%'.$filters['unit_code'].'%');
            });
        }

        if (! empty($filters['min_gpa'])) {
            $query->where('grade_points', '>=', $filters['min_gpa']);
        }

        if (! empty($filters['max_gpa'])) {
            $query->where('grade_points', '<=', $filters['max_gpa']);
        }
    }

    /**
     * Apply assessment filters
     */
    protected function applyAssessmentFilters($query, array $filters): void
    {
        if (! empty($filters['semester_id'])) {
            $query->whereHas('courseOffering', function ($q) use ($filters) {
                $q->where('semester_id', $filters['semester_id']);
            });
        }

        if (! empty($filters['assessment_type'])) {
            $query->whereHas('assessmentComponentDetail.assessmentComponent', function ($q) use ($filters) {
                $q->where('type', $filters['assessment_type']);
            });
        }

        if (! empty($filters['course_code'])) {
            $query->whereHas('courseOffering.unit', function ($q) use ($filters) {
                $q->where('code', 'like', '%'.$filters['course_code'].'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('submission_status', $filters['status']);
        }
    }

    /**
     * Calculate semester GPA
     */
    protected function calculateSemesterGPA(Collection $records): float
    {
        $completedRecords = $records->where('completion_status', 'completed');

        if ($completedRecords->isEmpty()) {
            return 0.0;
        }

        // sum of credit points * final percentage
        $totalQualityPoints = $completedRecords->sum(function ($record) {
            return $record->credit_points * $record->final_percentage;
        });
        $totalCreditPoints = $completedRecords->sum('credit_points');

        return $totalCreditPoints > 0 ? round($totalQualityPoints / $totalCreditPoints, 2) : 0.0;
    }

    /**
     * Calculate overall GPA
     */
    protected function calculateOverallGPA(Collection $records): float
    {
        if ($records->isEmpty()) {
            return 0.0;
        }

        $totalQualityPoints = $records->sum('quality_points');
        $totalCreditHours = $records->sum('credit_hours');

        return $totalCreditHours > 0 ? round($totalQualityPoints / $totalCreditHours, 2) : 0.0;
    }

    /**
     * Calculate consistency of GPA trend
     */
    protected function calculateConsistency(Collection $gpas): string
    {
        if ($gpas->count() < 3) {
            return 'insufficient_data';
        }

        $mean = $gpas->avg();
        $variance = $gpas->map(fn ($gpa) => pow($gpa - $mean, 2))->avg();
        $stdDev = sqrt($variance);

        return match (true) {
            $stdDev < 0.2 => 'very_consistent',
            $stdDev < 0.4 => 'consistent',
            $stdDev < 0.6 => 'moderate',
            default => 'inconsistent',
        };
    }

    /**
     * Format GPA trend data
     */
    protected function formatGPATrendData(Collection $gpaCalculations): array
    {
        return $gpaCalculations->map(function ($calculation) {
            return [
                'semester' => $calculation->semester->name,
                'semester_code' => $calculation->semester->code,
                'gpa' => round($calculation->gpa, 2),
                'credit_hours' => $calculation->credit_hours_earned,
                'quality_points' => $calculation->quality_points,
                'academic_standing' => $calculation->academic_standing,
                'date' => $calculation->created_at->toDateString(),
            ];
        })->toArray();
    }

    /**
     * Analyze GPA trend
     */
    protected function analyzeGPATrend(Collection $gpaCalculations): array
    {
        $gpas = $gpaCalculations->pluck('gpa');

        if ($gpas->count() < 2) {
            return [
                'direction' => 'insufficient_data',
                'consistency' => 'insufficient_data',
                'improvement_rate' => 0,
            ];
        }

        $latest = $gpas->last();
        $previous = $gpas->get($gpas->count() - 2);
        $change = $latest - $previous;

        return [
            'direction' => $change > 0.1 ? 'improving' : ($change < -0.1 ? 'declining' : 'stable'),
            'consistency' => $this->calculateConsistency($gpas),
            'improvement_rate' => round($change, 2),
            'average_gpa' => round($gpas->avg(), 2),
            'highest_gpa' => round($gpas->max(), 2),
            'lowest_gpa' => round($gpas->min(), 2),
        ];
    }

    /**
     * Predict future GPA based on trends
     */
    protected function predictFutureGPA(Collection $gpaCalculations): array
    {
        $gpas = $gpaCalculations->pluck('gpa');

        if ($gpas->count() < 3) {
            return [
                'next_semester_prediction' => null,
                'confidence' => 'low',
                'recommendation' => 'Insufficient data for prediction',
            ];
        }

        // Simple linear regression for prediction
        $n = $gpas->count();
        $x = range(1, $n);
        $y = $gpas->toArray();

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = array_sum(array_map(fn ($i) => $x[$i] * $y[$i], range(0, $n - 1)));
        $sumX2 = array_sum(array_map(fn ($val) => $val * $val, $x));

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        $nextGPA = $slope * ($n + 1) + $intercept;
        $nextGPA = max(0, min(4, $nextGPA)); // Clamp between 0 and 4

        return [
            'next_semester_prediction' => round($nextGPA, 2),
            'confidence' => $this->calculatePredictionConfidence($gpas),
            'recommendation' => $this->generateRecommendation($slope, $nextGPA),
        ];
    }

    /**
     * Calculate prediction confidence
     */
    protected function calculatePredictionConfidence(Collection $gpas): string
    {
        $consistency = $this->calculateConsistency($gpas);

        return match ($consistency) {
            'very_consistent', 'consistent' => 'high',
            'moderate' => 'medium',
            default => 'low',
        };
    }

    /**
     * Generate recommendation based on trend
     */
    protected function generateRecommendation(float $slope, float $predictedGPA): string
    {
        if ($slope > 0.1) {
            return 'Great progress! Continue with current study strategies.';
        } elseif ($slope < -0.1) {
            return 'Consider seeking academic support to improve performance.';
        } elseif ($predictedGPA < 2.0) {
            return 'Academic intervention recommended. Contact your advisor.';
        } else {
            return 'Maintain consistent study habits to sustain performance.';
        }
    }

    /**
     * Format assessment breakdown
     */
    protected function formatAssessmentBreakdown(Collection $assessmentScores): array
    {
        return $assessmentScores->map(function ($score) {
            return [
                'id' => $score->id,
                'title' => $score->assessmentComponentDetail->name,
                'type' => $score->assessmentComponentDetail->assessmentComponent->type,
                'weight' => $score->assessmentComponentDetail->weight,
                'max_score' => $score->max_score,
                'achieved_score' => $score->achieved_score,
                'percentage' => $score->max_score > 0
                    ? round(($score->achieved_score / $score->max_score) * 100, 1)
                    : 0,
                'due_date' => $score->due_date?->toDateString(),
                'submission_status' => $score->submission_status,
                'graded_at' => $score->graded_at?->toDateString(),
            ];
        })->toArray();
    }

    /**
     * Calculate grade breakdown
     */
    protected function calculateGradeBreakdown(Collection $assessmentScores): array
    {
        $totalWeight = $assessmentScores->sum('assessmentComponentDetail.weight');
        $weightedScore = 0;

        foreach ($assessmentScores as $score) {
            if ($score->achieved_score !== null && $score->max_score > 0) {
                $percentage = ($score->achieved_score / $score->max_score) * 100;
                $weightedScore += $percentage * ($score->assessmentComponentDetail->weight / 100);
            }
        }

        return [
            'total_weight_assessed' => $totalWeight,
            'weighted_average' => round($weightedScore, 1),
            'projected_final_grade' => $this->calculateGradeEquivalent($weightedScore, 100),
            'assessments_completed' => $assessmentScores->whereNotNull('achieved_score')->count(),
            'assessments_pending' => $assessmentScores->whereNull('achieved_score')->count(),
        ];
    }

    /**
     * Analyze course performance
     */
    protected function analyzeCoursePerformance(Collection $assessmentScores): array
    {
        $completedAssessments = $assessmentScores->whereNotNull('achieved_score');

        if ($completedAssessments->isEmpty()) {
            return [
                'performance_level' => 'no_data',
                'strengths' => [],
                'areas_for_improvement' => [],
                'trend' => 'no_data',
            ];
        }

        $averagePercentage = $completedAssessments->avg(function ($score) {
            return $score->max_score > 0 ? ($score->achieved_score / $score->max_score) * 100 : 0;
        });

        $performanceByType = $completedAssessments->groupBy('assessmentComponentDetail.assessmentComponent.type')
            ->map(function ($scores) {
                return $scores->avg(function ($score) {
                    return $score->max_score > 0 ? ($score->achieved_score / $score->max_score) * 100 : 0;
                });
            });

        return [
            'performance_level' => $this->getPerformanceLevel($averagePercentage),
            'average_percentage' => round($averagePercentage, 1),
            'performance_by_type' => $performanceByType->map(fn ($avg) => round($avg, 1))->toArray(),
            'strengths' => $this->identifyStrengths($performanceByType),
            'areas_for_improvement' => $this->identifyWeaknesses($performanceByType),
            'trend' => $this->analyzeAssessmentTrend($completedAssessments),
        ];
    }

    /**
     * Get performance level based on percentage
     */
    protected function getPerformanceLevel(float $percentage): string
    {
        return match (true) {
            $percentage >= 85 => 'excellent',
            $percentage >= 75 => 'good',
            $percentage >= 65 => 'satisfactory',
            $percentage >= 50 => 'needs_improvement',
            default => 'unsatisfactory',
        };
    }

    /**
     * Calculate grade equivalent
     */
    protected function calculateGradeEquivalent(float $score, float $maxScore): string
    {
        if ($maxScore <= 0) {
            return 'N';
        }

        $percentage = ($score / $maxScore) * 100;

        return match (true) {
            $percentage >= 80 => 'HD',
            $percentage >= 70 => 'D',
            $percentage >= 60 => 'C',
            $percentage >= 50 => 'P',
            default => 'N',
        };
    }

    /**
     * Format assessments
     */
    protected function formatAssessments(Collection $assessments): array
    {
        return $assessments->map(function ($assessment) {
            return [
                'id' => $assessment->id,
                'title' => $assessment->assessmentComponentDetail->name,
                'course_code' => $assessment->courseOffering->unit->code,
                'course_name' => $assessment->courseOffering->unit->name,
                'type' => $assessment->assessmentComponentDetail->assessmentComponent->type,
                'weight' => $assessment->assessmentComponentDetail->weight,
                'max_score' => $assessment->max_score,
                'achieved_score' => $assessment->achieved_score,
                'percentage' => $assessment->achieved_score && $assessment->max_score > 0
                    ? round(($assessment->achieved_score / $assessment->max_score) * 100, 1)
                    : null,
                'due_date' => $assessment->due_date?->toDateString(),
                'submission_status' => $assessment->submission_status,
                'graded_at' => $assessment->graded_at?->toDateString(),
                'semester' => $assessment->courseOffering->semester->name,
            ];
        })->toArray();
    }

    /**
     * Calculate assessment summary
     */
    protected function calculateAssessmentSummary(Collection $assessments): array
    {
        $completed = $assessments->whereNotNull('achieved_score');
        $pending = $assessments->whereNull('achieved_score');

        return [
            'total_assessments' => $assessments->count(),
            'completed_assessments' => $completed->count(),
            'pending_assessments' => $pending->count(),
            'average_score' => $completed->count() > 0
                ? round($completed->avg(function ($assessment) {
                    return $assessment->max_score > 0
                        ? ($assessment->achieved_score / $assessment->max_score) * 100
                        : 0;
                }), 1)
                : 0,
        ];
    }

    /**
     * Get upcoming assessments
     */
    protected function getUpcomingAssessments(Student $student): array
    {
        $upcomingAssessments = AssessmentComponentDetailScore::where('student_id', $student->id)
            ->whereNull('achieved_score')
            ->where('due_date', '>=', now())
            ->with([
                'assessmentComponentDetail.assessmentComponent',
                'courseOffering.unit',
            ])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        return $upcomingAssessments->map(function ($assessment) {
            return [
                'id' => $assessment->id,
                'title' => $assessment->assessmentComponentDetail->name,
                'course_code' => $assessment->courseOffering->unit->code,
                'type' => $assessment->assessmentComponentDetail->assessmentComponent->type,
                'weight' => $assessment->assessmentComponentDetail->weight,
                'due_date' => $assessment->due_date?->toDateString(),
                'days_until_due' => $assessment->due_date ? now()->diffInDays($assessment->due_date, false) : null,
                'urgency' => $this->calculateUrgency($assessment->due_date),
            ];
        })->toArray();
    }

    /**
     * Analyze performance by assessment type
     */
    protected function analyzePerformanceByAssessmentType(Collection $assessments): array
    {
        $completedAssessments = $assessments->whereNotNull('achieved_score');

        return $completedAssessments->groupBy('assessmentComponentDetail.assessmentComponent.type')
            ->map(function ($typeAssessments, $type) {
                $averagePercentage = $typeAssessments->avg(function ($assessment) {
                    return $assessment->max_score > 0
                        ? ($assessment->achieved_score / $assessment->max_score) * 100
                        : 0;
                });

                return [
                    'type' => $type,
                    'count' => $typeAssessments->count(),
                    'average_percentage' => round($averagePercentage, 1),
                    'performance_level' => $this->getPerformanceLevel($averagePercentage),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Calculate urgency based on due date
     */
    protected function calculateUrgency(?string $dueDate): string
    {
        if (! $dueDate) {
            return 'unknown';
        }

        $due = Carbon::parse($dueDate);
        $daysUntil = now()->diffInDays($due, false);

        return match (true) {
            $daysUntil < 0 => 'overdue',
            $daysUntil <= 1 => 'critical',
            $daysUntil <= 3 => 'high',
            $daysUntil <= 7 => 'medium',
            default => 'low',
        };
    }

    /**
     * Identify strengths from performance data
     */
    protected function identifyStrengths(Collection $performanceByType): array
    {
        return $performanceByType->filter(fn ($avg) => $avg >= 75)
            ->keys()
            ->toArray();
    }

    /**
     * Identify weaknesses from performance data
     */
    protected function identifyWeaknesses(Collection $performanceByType): array
    {
        return $performanceByType->filter(fn ($avg) => $avg < 65)
            ->keys()
            ->toArray();
    }

    /**
     * Analyze assessment trend
     */
    protected function analyzeAssessmentTrend(Collection $assessments): string
    {
        if ($assessments->count() < 3) {
            return 'insufficient_data';
        }

        $orderedAssessments = $assessments->sortBy('due_date');
        $scores = $orderedAssessments->map(function ($assessment) {
            return $assessment->max_score > 0
                ? ($assessment->achieved_score / $assessment->max_score) * 100
                : 0;
        });

        $firstHalf = $scores->take(ceil($scores->count() / 2))->avg();
        $secondHalf = $scores->skip(floor($scores->count() / 2))->avg();

        $change = $secondHalf - $firstHalf;

        return match (true) {
            $change > 5 => 'improving',
            $change < -5 => 'declining',
            default => 'stable',
        };
    }

    /**
     * Build grades by semester with curriculum roadmap
     */
    protected function buildGradesBySemester(Student $student, Collection $curriculumRecords): array
    {
        if (! $student->curriculumVersion) {
            return [];
        }

        // Get all curriculum units grouped by semester_number (relative semester in program)
        $curriculumUnits = $student->curriculumVersion
            ->curriculumUnits()
            ->with(['semester', 'unit'])
            ->get();

        $curriculumSemesters = $curriculumUnits->groupBy('semester_number');

        if ($curriculumSemesters->isEmpty()) {
            return [];
        }

        return $curriculumSemesters->map(function ($semesterUnits, $semesterNumber) use ($student, $curriculumRecords) {
            $semester = $semesterUnits->first()->semester;

            // Get academic records for units in this logical semester
            $unitIds = $semesterUnits->pluck('unit_id')->toArray();
            $semesterRecords = $curriculumRecords->filter(fn ($r) => in_array($r->unit_id, $unitIds));

            // Format all curriculum units (với hoặc không có điểm)
            $allUnits = $semesterUnits->map(function ($cu) use ($semesterRecords) {
                $record = $semesterRecords->firstWhere('unit_id', $cu->unit_id);

                return [
                    'curriculum_unit_id' => $cu->id,
                    'unit_id' => $cu->unit_id,
                    'unit_code' => $cu->unit->code,
                    'unit_name' => $cu->unit->name,
                    'unit_scope' => $cu->unit_scope,
                    'year_level' => $cu->year_level,
                    'semester_number' => $cu->semester_number,
                    'credit_hours' => $record?->credit_hours ?? $cu->unit->credit_points,
                    'final_percentage' => $record?->final_percentage,
                    'final_grade' => $record?->final_letter_grade,
                    'numeric_grade' => $record?->final_numeric_grade,
                    'grade_points' => $record?->grade_points,
                    'grade_display' => $record ? $this->gradeDisplayPresenter->present($record) : null,
                    'completion_status' => $record?->completion_status ?? 'not_enrolled',
                    'completion_date' => $record?->completion_date?->toDateString(),
                    'is_passed' => $record?->is_passed ?? false,
                    'override_pass' => $record?->override_pass ?? false,
                    'override_reason' => $record?->override_reason,
                    'course_offering_id' => $record?->course_offering_id,
                    'lecturer' => $record?->courseOffering?->lecture?->full_name ?? null,
                    'section_code' => $record?->courseOffering?->section_code ?? null,
                ];
            })->values();

            // Get modules for this semester using semester_number
            $modulesData = $this->getModulesForSemester($student, $semesterNumber);

            return [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'semester_number' => $semesterNumber,
                ],
                'curriculum_units' => $allUnits->toArray(),
                'modules' => $modulesData,
                'semester_summary' => $this->buildSemesterSummary($student, $semester, $semesterRecords),
            ];
        })->sortBy('semester.semester_number')->values()->toArray();
    }

    /**
     * Build semester summary, preferring stored GPA calculations when available
     */
    protected function buildSemesterSummary(Student $student, Semester $semester, Collection $records): array
    {
        $gpaCalculation = GpaCalculation::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->current()
            ->first();

        if ($gpaCalculation) {
            $completed = $records->where('completion_status', 'completed');

            return [
                'total_units' => $records->count(),
                'completed_units' => $completed->count(),
                'total_credits' => (float) $gpaCalculation->semester_credit_points,
                'earned_credits' => (float) $gpaCalculation->semester_credit_points_earned,
                'semester_gpa' => (float) $gpaCalculation->semester_gpa,
            ];
        }

        return $this->calculateSemesterSummary($records);
    }

    /**
     * Build simple summary statistics (EGC units excluded - only prerequisite)
     */
    protected function buildSimpleSummary(Student $student, array $gradesBySemester, Collection $egcRecords): array
    {
        // Count all curriculum units (EGC excluded from statistics)
        $allCurriculumUnits = collect($gradesBySemester)->flatMap(fn ($s) => $s['curriculum_units']);

        // Count all modules
        $allModules = collect($gradesBySemester)->flatMap(fn ($s) => $s['modules'] ?? []);

        // Unit statistics (only curriculum units, EGC not included)
        $totalUnits = $allCurriculumUnits->count();
        $notEnrolledUnits = $allCurriculumUnits->where('completion_status', 'not_enrolled')->count();
        $inProgressUnits = $allCurriculumUnits->where('completion_status', 'in_progress')->count();
        $passedUnits = $allCurriculumUnits->where('is_passed', true)->count();
        $failedUnits = $allCurriculumUnits->where('completion_status', 'completed')
            ->where('is_passed', false)->count();

        // Module statistics
        $totalModules = $allModules->count();
        $notStartedModules = $allModules->where('status', 'not_started')->count();
        $inProgressModules = $allModules->where('status', 'in_progress')->count();
        $passedModules = $allModules->where('status', 'passed')->count();
        $failedModules = $allModules->where('status', 'failed')->count();

        // Credits (only curriculum units, EGC excluded)
        $totalCreditsAttempted = $allCurriculumUnits->sum('credit_hours');
        $totalCreditsEarned = $allCurriculumUnits->where('is_passed', true)->sum('credit_hours');

        return [
            'units' => [
                'total' => $totalUnits,
                'not_enrolled' => $notEnrolledUnits,
                'in_progress' => $inProgressUnits,
                'passed' => $passedUnits,
                'failed' => $failedUnits,
            ],
            'modules' => [
                'total' => $totalModules,
                'not_started' => $notStartedModules,
                'in_progress' => $inProgressModules,
                'passed' => $passedModules,
                'failed' => $failedModules,
            ],
            'credits' => [
                'total_attempted' => $totalCreditsAttempted,
                'total_earned' => $totalCreditsEarned,
            ],
        ];
    }

    /**
     * Get ALL modules for a specific semester from curriculum
     */
    protected function getModulesForSemester(Student $student, int $semesterNumber): array
    {
        if (! $student->curriculumVersion) {
            return [];
        }

        // Get ALL modules that belong to this semester from curriculum
        $curriculumModules = $student->curriculumVersion
            ->curriculumModules()
            ->where('semester_number', $semesterNumber)
            ->with(['module', 'module.units'])
            ->get();

        if ($curriculumModules->isEmpty()) {
            return [];
        }

        return $curriculumModules->map(function ($curriculumModule) use ($student) {
            $module = $curriculumModule->module;

            // Get ALL sub-units from module_units pivot
            $moduleUnits = $module->units;
            $moduleUnitIds = $moduleUnits->pluck('id')->toArray();

            // Get academic records for these units (nếu có)
            $allModuleRecords = $student->academicRecords()
                ->whereHas('courseOffering', function ($q) use ($moduleUnitIds) {
                    $q->whereIn('unit_id', $moduleUnitIds);
                })
                ->with(['courseOffering', 'unit', 'semester'])
                ->get();

            // Calculate module grade from completed graded units
            $moduleGrade = $this->calculateModuleGrade($module, $allModuleRecords);

            // Format ALL sub-units (có điểm hoặc null)
            $subUnits = $moduleUnits->map(function ($unit) use ($allModuleRecords) {
                $record = $allModuleRecords->firstWhere('unit_id', $unit->id);
                $gradingType = $record?->courseOffering?->grading_type ?? 'grade';

                return [
                    'unit_id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credits' => $unit->credit_points,
                    'final_percentage' => $record?->final_percentage,
                    'final_grade' => $record?->final_letter_grade,
                    'numeric_grade' => $record?->final_numeric_grade,
                    'grade_display' => $record ? $this->gradeDisplayPresenter->present($record) : null,
                    'status' => $record?->completion_status ?? 'not_enrolled',
                    'completion_date' => $record?->completion_date?->toDateString(),
                    'is_passed' => $record?->is_passed ?? false,
                    'override_pass' => $record?->override_pass ?? false,
                    'override_reason' => $record?->override_reason,
                    'course_offering_id' => $record?->course_offering_id,
                    'grading_type' => $gradingType,
                    'included_in_module_average' => $gradingType === 'grade',
                    'weight' => $unit->pivot?->weight ?? null,
                    'order' => $unit->pivot?->order ?? 0,
                    'semester_taken' => $record?->semester?->name ?? null,
                ];
            })->sortBy('order')->values()->toArray();

            // Overall completion stats
            $totalUnits = $moduleUnits->count();
            $completedUnits = $allModuleRecords->where('completion_status', 'completed')->count();

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
                'sub_units' => $subUnits,
                'completion' => [
                    'completed_units' => $completedUnits,
                    'total_units' => $totalUnits,
                    'percentage' => $totalUnits > 0 ? round(($completedUnits / $totalUnits) * 100) : 0,
                ],
                'status' => $this->determineModuleStatus($allModuleRecords, $totalUnits),
                'grading_type' => $module->grading_type ?? 'grade',
            ];
        })->toArray();
    }

    /**
     * Calculate module grade from all enrolled units
     */
    protected function calculateModuleGrade($module, Collection $allModuleRecords): ?float
    {
        // Only calculate from graded units with completed status
        $gradedRecords = $allModuleRecords->filter(
            fn ($r) => $r->courseOffering &&
                $r->courseOffering->grading_type === 'grade' &&
                $r->completion_status === 'completed' &&
                $r->final_percentage !== null
        );

        if ($gradedRecords->isEmpty()) {
            return null;
        }

        // Check if weights are used
        $firstUnit = $module->units->first();
        $usesWeights = $firstUnit && isset($firstUnit->pivot->weight) && $firstUnit->pivot->weight !== null;

        if ($usesWeights) {
            // Weighted average
            $totalWeight = 0;
            $weightedSum = 0;

            foreach ($gradedRecords as $record) {
                $unit = $module->units->find($record->unit_id);
                if ($unit && isset($unit->pivot->weight) && $unit->pivot->weight) {
                    $weight = $unit->pivot->weight;
                    $totalWeight += $weight;
                    $weightedSum += $record->final_percentage * $weight;
                }
            }

            return $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : null;
        }

        // Simple average
        return round($gradedRecords->avg('final_percentage'), 2);
    }

    /**
     * Build EGC units simple array
     */
    protected function buildEGCUnits(Collection $egcRecords): array
    {
        return $egcRecords->map(function ($record) {
            return [
                'id' => $record->id,
                'unit_id' => $record->unit_id,
                'unit_code' => $record->unit->code,
                'unit_name' => $record->unit->name,
                'unit_type' => $record->unit->unit_type,
                'credit_hours' => $record->credit_hours,
                'credit_points' => $record->unit->credit_points,
                'final_percentage' => $record->final_percentage,
                'final_grade' => $record->final_letter_grade,
                'numeric_grade' => $record->final_numeric_grade,
                'grade_display' => $this->gradeDisplayPresenter->present($record),
                'completion_status' => $record->completion_status,
                'completion_date' => $record->completion_date?->toDateString(),
                'is_passed' => $record->is_passed ?? false,
                'override_pass' => $record->override_pass ?? false,
                'override_reason' => $record->override_reason,
                'course_offering_id' => $record->course_offering_id,
                'semester' => $record->semester?->name,
                'lecturer' => $record->courseOffering?->lecture?->full_name ?? null,
            ];
        })->values()->toArray();
    }

    /**
     * Determine module completion status
     */
    protected function determineModuleStatus(Collection $records, int $totalUnits): string
    {
        $completedCount = $records->where('completion_status', 'completed')->count();

        if ($completedCount === 0) {
            return 'not_started';
        }

        if ($completedCount < $totalUnits) {
            return 'in_progress';
        }

        // All completed - check if all passed
        $completedRecords = $records->where('completion_status', 'completed');
        $allPassed = $completedRecords->every(function ($record) {
            return $record->final_letter_grade &&
                ! in_array(strtoupper($record->final_letter_grade), ['F', 'FAIL', 'N']);
        });

        return $allPassed ? 'passed' : 'failed';
    }

    /**
     * Calculate semester summary from records
     */
    protected function calculateSemesterSummary(Collection $records): array
    {
        $completed = $records->where('completion_status', 'completed');

        return [
            'total_units' => $records->count(),
            'completed_units' => $completed->count(),
            'total_credits' => $records->sum('credit_points'),
            'earned_credits' => $completed->sum('credit_points_earned') ?? 0,
            'semester_gpa' => $this->calculateSemesterGPA($records),
        ];
    }
}
