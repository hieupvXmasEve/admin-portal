<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GPACalculationService
{
    /**
     * Calculate current GPA for student
     */
    public function calculateCurrentGPA(Student $student): array
    {
        $academicRecords = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->where('excluded_from_gpa', false)
            ->get();

        if ($academicRecords->isEmpty()) {
            return [
                'gpa' => 0.0,
                'quality_points' => 0.0,
                'credit_hours_attempted' => 0.0,
                'credit_hours_earned' => 0.0,
                'total_courses' => 0,
            ];
        }

        $totalQualityPoints = $academicRecords->sum('quality_points');
        $totalCreditHours = $academicRecords->sum('credit_hours');
        $totalCreditHoursEarned = $academicRecords->sum('credit_hours_earned');

        $gpa = $totalCreditHours > 0 ? $totalQualityPoints / $totalCreditHours : 0.0;

        return [
            'gpa' => round($gpa, 2),
            'quality_points' => $totalQualityPoints,
            'credit_hours_attempted' => $totalCreditHours,
            'credit_hours_earned' => $totalCreditHoursEarned,
            'total_courses' => $academicRecords->count(),
        ];
    }

    /**
     * Calculate semester GPA for student
     */
    public function calculateSemesterGPA(Student $student, Semester $semester): array
    {
        $academicRecords = $student->academicRecords()
            ->where('semester_id', $semester->id)
            ->where('completion_status', 'completed')
            ->where('excluded_from_gpa', false)
            ->get();

        if ($academicRecords->isEmpty()) {
            return [
                'semester_id' => $semester->id,
                'semester_name' => $semester->name,
                'gpa' => 0.0,
                'quality_points' => 0.0,
                'credit_hours' => 0.0,
                'courses_completed' => 0,
            ];
        }

        $totalQualityPoints = $academicRecords->sum('quality_points');
        $totalCreditHours = $academicRecords->sum('credit_hours');

        $gpa = $totalCreditHours > 0 ? $totalQualityPoints / $totalCreditHours : 0.0;

        return [
            'semester_id' => $semester->id,
            'semester_name' => $semester->name,
            'gpa' => round($gpa, 2),
            'quality_points' => $totalQualityPoints,
            'credit_hours' => $totalCreditHours,
            'courses_completed' => $academicRecords->count(),
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
                    'gpa' => round($calculation->semester_gpa, 2),
                    'cumulative_gpa' => round($calculation->cumulative_gpa, 2),
                    'credit_hours' => $calculation->semester_credits_earned,
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
        $gradeDistribution = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->select('final_letter_grade', DB::raw('count(*) as count'))
            ->groupBy('final_letter_grade')
            ->pluck('count', 'final_letter_grade')
            ->toArray();

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
                fn($count) => round(($count / $totalCourses) * 100, 1),
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
                'semester_gpa' => 0.0,
                'cumulative_gpa' => 0.0,
                'required_gpa' => 2.0,
                'meets_requirement' => false,
                'warning_level' => null,
            ];
        }

        $standing = $this->determineAcademicStanding($latestGPA->cumulative_gpa);

        return [
            'standing' => $standing,
            'semester_gpa' => round($latestGPA->semester_gpa, 2),
            'cumulative_gpa' => round($latestGPA->cumulative_gpa, 2),
            'required_gpa' => 2.0, // Default for now
            'meets_requirement' => $latestGPA->cumulative_gpa >= 2.0,
            'warning_level' => $this->getWarningLevel($latestGPA->cumulative_gpa),
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

        $gpas = $gpaCalculations->pluck('semester_gpa');
        $latest = $gpas->last();
        $previous = $gpas->get($gpas->count() - 2);

        $change = $latest - $previous;
        $direction = $change > 0.1 ? 'improving' : ($change < -0.1 ? 'declining' : 'stable');

        // Calculate consistency (standard deviation)
        $mean = $gpas->avg();
        $variance = $gpas->map(fn($gpa) => pow($gpa - $mean, 2))->avg();
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
        return match (true) {
            $gpa >= 3.5 => 'excellent',
            $gpa >= 3.0 => 'good',
            $gpa >= 2.5 => 'satisfactory',
            $gpa >= 2.0 => 'probation',
            default => 'unsatisfactory',
        };
    }

    /**
     * Get warning level based on GPA
     */
    protected function getWarningLevel(float $gpa): ?string
    {
        return match (true) {
            $gpa < 1.5 => 'critical',
            $gpa < 2.0 => 'warning',
            $gpa < 2.5 => 'watch',
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
