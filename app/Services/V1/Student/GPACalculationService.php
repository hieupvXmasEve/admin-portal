<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Shared\Contracts\Academic\TranscriptEntryGpaReader;
use Illuminate\Support\Collection;

class GPACalculationService
{
    public function __construct(private readonly TranscriptEntryGpaReader $transcriptEntries) {}

    public function calculateCurrentGPA(Student $student): array
    {
        $gpaCalculation = GpaCalculation::query()
            ->where('student_id', $student->id)
            ->current()
            ->orderBy('semester_id', 'desc')
            ->first();

        if ($gpaCalculation) {
            return [
                'gpa' => (float) $gpaCalculation->cumulative_gpa,
                'quality_points' => (float) $gpaCalculation->cumulative_quality_points,
                'credit_hours_attempted' => (float) $gpaCalculation->cumulative_credit_points,
                'credit_hours_earned' => (float) $gpaCalculation->cumulative_credit_points_earned,
                'total_courses' => count($this->transcriptEntries->throughSemester($student->id, null)),
                'from_stored_calculation' => true,
            ];
        }

        $academicRecords = collect($this->transcriptEntries->throughSemester($student->id, null));

        if ($academicRecords->isEmpty()) {
            return [
                'gpa' => 0.0,
                'quality_points' => 0.0,
                'credit_hours_attempted' => 0.0,
                'credit_hours_earned' => 0.0,
                'total_courses' => 0,
            ];
        }

        $totalQualityPoints = $academicRecords->sum('qualityPoints');
        $totalCreditPoints = $academicRecords->sum('creditPoints');
        $totalCreditPointsEarned = $academicRecords->sum('creditPointsEarned');

        $gpa = $totalCreditPoints > 0 ? $totalQualityPoints / $totalCreditPoints : 0.0;

        return [
            'gpa' => round($gpa, 2),
            'quality_points' => $totalQualityPoints,
            'credit_hours_attempted' => $totalCreditPoints,
            'credit_hours_earned' => $totalCreditPointsEarned,
            'total_courses' => $academicRecords->count(),
            'from_stored_calculation' => false,
        ];
    }

    public function calculateSemesterGPA(Student $student, Semester $semester): array
    {
        $gpaCalculation = GpaCalculation::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->current()
            ->first();

        if ($gpaCalculation) {
            return [
                'semester_id' => $semester->id,
                'semester_name' => $semester->name,
                'gpa' => (float) $gpaCalculation->semester_gpa,
                'quality_points' => (float) $gpaCalculation->semester_quality_points,
                'credit_hours' => (float) $gpaCalculation->semester_credit_points,
                'earned_credits' => (float) $gpaCalculation->semester_credit_points_earned,
                'courses_completed' => count($this->transcriptEntries->forSemester($student->id, $semester->id)),
                'from_stored_calculation' => true,
            ];
        }

        $academicRecords = collect($this->transcriptEntries->forSemester($student->id, $semester->id));

        if ($academicRecords->isEmpty()) {
            return [
                'semester_id' => $semester->id,
                'semester_name' => $semester->name,
                'gpa' => 0.0,
                'quality_points' => 0.0,
                'credit_hours' => 0.0,
                'earned_credits' => 0.0,
                'courses_completed' => 0,
            ];
        }

        $totalQualityPoints = $academicRecords->sum('qualityPoints');
        $totalCreditPoints = $academicRecords->sum('creditPoints');
        $totalCreditPointsEarned = $academicRecords->sum('creditPointsEarned');

        $gpa = $totalCreditPoints > 0 ? $totalQualityPoints / $totalCreditPoints : 0.0;

        return [
            'semester_id' => $semester->id,
            'semester_name' => $semester->name,
            'gpa' => round($gpa, 2),
            'quality_points' => $totalQualityPoints,
            'credit_hours' => $totalCreditPoints,
            'earned_credits' => $totalCreditPointsEarned,
            'courses_completed' => $academicRecords->count(),
            'from_stored_calculation' => false,
        ];
    }

    /**
     * Get GPA trend over time
     */
    public function getGPATrend(Student $student, int $semesterCount = 6): array
    {
        $gpaCalculations = GpaCalculation::where('student_id', $student->id)
            ->with('semester')
            ->orderBy('created_at', 'desc')
            ->limit($semesterCount)
            ->get()
            ->reverse()
            ->values();

        return [
            'trend_data' => $gpaCalculations->map(function ($calculation) {
                return [
                    'semester' => $calculation->semester->name,
                    'semester_code' => $calculation->semester->code,
                    'gpa' => round((float) $calculation->semester_gpa, 2),
                    'cumulative_gpa' => round((float) $calculation->cumulative_gpa, 2),
                    'credit_hours' => $calculation->semester_credit_points_earned,
                    'academic_standing' => $calculation->academic_standing,
                    'date' => $calculation->created_at->toDateString(),
                ];
            }),
            'trend_analysis' => $this->analyzeTrend($gpaCalculations),
        ];
    }

    /**
     * Get grade distribution for student
     */
    public function getGradeDistribution(Student $student): array
    {
        $gradeDistribution = collect($this->transcriptEntries->throughSemester($student->id, null))
            ->countBy('finalLetterGrade')
            ->all();

        // Ensure all grade categories are present
        $standardGrades = ['HD', 'D', 'C', 'P', 'N', 'F'];
        $distribution = [];

        foreach ($standardGrades as $grade) {
            $distribution[$grade] = $gradeDistribution[$grade] ?? 0;
        }

        $totalCourses = array_sum($distribution);

        return [
            'distribution' => $distribution,
            'percentages' => $totalCourses > 0 ? array_map(
                fn ($count) => round(($count / $totalCourses) * 100, 1),
                $distribution
            ) : array_fill_keys($standardGrades, 0),
            'total_courses' => $totalCourses,
        ];
    }

    /**
     * Check academic standing
     */
    public function getAcademicStanding(Student $student): array
    {
        $latestGPA = GpaCalculation::where('student_id', $student->id)
            ->where('is_current', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $latestGPA) {
            return [
                'standing' => 'unknown',
                'gpa' => 0.0,
                'semester_gpa' => 0.0,
                'cumulative_gpa' => 0.0,
                'required_gpa' => 50.0,
                'meets_requirement' => false,
                'warning_level' => null,
            ];
        }

        $cumulativeGpa = (float) $latestGPA->cumulative_gpa;
        $semesterGpa = (float) $latestGPA->semester_gpa;

        $standing = $latestGPA->academic_standing ?: $this->determineAcademicStanding($cumulativeGpa);

        return [
            'standing' => $standing,
            'gpa' => round($cumulativeGpa, 2),
            'semester_gpa' => round($semesterGpa, 2),
            'cumulative_gpa' => round($cumulativeGpa, 2),
            'required_gpa' => 50.0,
            'meets_requirement' => $cumulativeGpa >= 50.0,
            'warning_level' => $this->getWarningLevel($cumulativeGpa),
        ];
    }

    /**
     * Analyze GPA trend
     */
    protected function analyzeTrend(Collection $gpaCalculations): array
    {
        if ($gpaCalculations->count() < 2) {
            return [
                'direction' => 'insufficient_data',
                'change' => 0.0,
                'consistency' => 'unknown',
            ];
        }

        $gpas = $gpaCalculations->pluck('semester_gpa')->map(fn ($gpa) => (float) $gpa);
        $latest = $gpas->last();
        $previous = $gpas->get($gpas->count() - 2);

        $change = $latest - $previous;
        $direction = $change > 0.1 ? 'improving' : ($change < -0.1 ? 'declining' : 'stable');

        // Calculate consistency (standard deviation)
        $mean = $gpas->avg();
        $variance = $gpas->map(fn ($gpa) => pow($gpa - $mean, 2))->avg();
        $stdDev = sqrt($variance);

        $consistency = $stdDev < 0.2 ? 'consistent' : ($stdDev < 0.5 ? 'moderate' : 'variable');

        return [
            'direction' => $direction,
            'change' => round($change, 2),
            'consistency' => $consistency,
            'standard_deviation' => round($stdDev, 2),
        ];
    }

    /**
     * Determine academic standing based on GPA
     */
    protected function determineAcademicStanding(float $gpa): string
    {
        return $gpa >= 50.0 ? 'normal' : 'warning';
    }

    /**
     * Get warning level based on GPA
     */
    protected function getWarningLevel(float $gpa): ?string
    {
        return match (true) {
            $gpa < 40.0 => 'critical',
            $gpa < 50.0 => 'warning',
            default => null,
        };
    }

    /**
     * Calculate projected GPA with additional courses
     */
    public function projectGPA(Student $student, array $projectedGrades): array
    {
        $currentGPA = $this->calculateCurrentGPA($student);

        $currentQualityPoints = $currentGPA['quality_points'];
        $currentCreditHours = $currentGPA['credit_hours_attempted'];

        $additionalQualityPoints = 0;
        $additionalCreditHours = 0;

        foreach ($projectedGrades as $grade) {
            $gradePoints = $this->getGradePoints($grade['letter_grade']);
            $creditHours = $grade['credit_hours'];

            $additionalQualityPoints += $gradePoints * $creditHours;
            $additionalCreditHours += $creditHours;
        }

        $totalQualityPoints = $currentQualityPoints + $additionalQualityPoints;
        $totalCreditHours = $currentCreditHours + $additionalCreditHours;

        $projectedGPA = $totalCreditHours > 0 ? $totalQualityPoints / $totalCreditHours : 0.0;

        return [
            'current_gpa' => $currentGPA['gpa'],
            'projected_gpa' => round($projectedGPA, 2),
            'gpa_change' => round($projectedGPA - $currentGPA['gpa'], 2),
            'additional_credit_hours' => $additionalCreditHours,
        ];
    }

    /**
     * Get grade points for letter grade
     */
    protected function getGradePoints(string $letterGrade): float
    {
        return match (strtoupper($letterGrade)) {
            'HD' => 4.0,
            'D' => 3.0,
            'C' => 2.0,
            'P' => 1.0,
            'N', 'F' => 0.0,
            default => 0.0,
        };
    }
}
