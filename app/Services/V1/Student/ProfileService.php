<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\Semester;
use App\Models\Student;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class ProfileService
{
    /**
     * Get study plan
     */
    public function getStudyPlan(Student $student): array
    {
        $cacheKey = "study_plan:student:{$student->id}";

        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $currentSemester = $this->resolveCurrentSemester();
            $completedUnits = $this->getCompletedUnits($student);
            $currentEnrollments = $this->getCurrentEnrollments($student);
            $remainingRequirements = $this->getRemainingRequirements($student);

            return [
                'current_semester' => $currentSemester ? [
                    'id' => $currentSemester->id,
                    'name' => $currentSemester->name,
                    'code' => $currentSemester->code,
                ] : null,
                'completed_units' => $completedUnits,
                'current_enrollments' => $currentEnrollments,
                'remaining_requirements' => $remainingRequirements,
                'graduation_timeline' => $this->calculateGraduationTimeline($student),
                'recommended_next_units' => $this->getRecommendedNextUnits($student),
            ];
        });
    }

    /**
     * Get academic history
     */
    public function getAcademicHistory(Student $student): array
    {
        $cacheKey = "academic_history:student:{$student->id}";

        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $academicRecords = $student->academicRecords()
                ->with(['unit', 'semester', 'courseOffering.lecturer'])
                ->orderBy('semester_id', 'desc')
                ->get();

            return [
                'academic_records' => $this->formatAcademicRecords($academicRecords),
                'semester_summary' => $this->calculateSemesterSummary($academicRecords),
                'gpa_history' => $this->getGPAHistory($student),
                'credit_progression' => $this->getCreditProgression($student),
                'academic_achievements' => $this->getAcademicAchievements($student),
            ];
        });
    }

    /**
     * Get completed units
     */
    protected function getCompletedUnits(Student $student): array
    {
        return $student->academicRecords()
            ->where('completion_status', 'completed')
            ->with(['unit', 'semester'])
            ->get()
            ->map(function ($record) {
                return [
                    'unit_code' => $record->unit?->code,
                    'unit_name' => $record->unit?->name,
                    'credit_hours' => $record->credit_hours,
                    'grade' => $record->final_letter_grade,
                    'semester' => $record->semester?->name,
                    'completion_date' => $record->completion_date?->toDateString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get current enrollments
     */
    protected function getCurrentEnrollments(Student $student): array
    {
        $currentSemester = $this->resolveCurrentSemester();

        if (! $currentSemester) {
            return [];
        }

        return $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->where('registration_status', 'registered')
            ->with(['courseOffering.unit'])
            ->get()
            ->map(function ($registration) {
                return [
                    'unit_code' => $registration->courseOffering?->unit?->code,
                    'unit_name' => $registration->courseOffering?->unit?->name,
                    'credit_hours' => $registration->courseOffering?->credit_hours,
                    'registration_date' => $registration->registration_date?->toDateString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get remaining requirements
     */
    protected function getRemainingRequirements(Student $student): array
    {
        // This would calculate remaining curriculum requirements
        // Implementation depends on your curriculum structure
        return [
            'core_units' => [],
            'elective_units' => [],
            'total_credits_remaining' => 0,
        ];
    }

    /**
     * Calculate graduation timeline
     */
    protected function calculateGraduationTimeline(Student $student): array
    {
        $totalCreditsRequired = $student->curriculumVersion?->total_credit_hours ?? 0;
        $creditsEarned = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->sum('credit_hours_earned');

        $creditsRemaining = $totalCreditsRequired - $creditsEarned;
        $averageCreditsPerSemester = 18; // Typical full-time load

        $semestersRemaining = $creditsRemaining > 0
            ? (int) ceil($creditsRemaining / $averageCreditsPerSemester)
            : 0;

        return [
            'credits_remaining' => $creditsRemaining,
            'semesters_remaining' => $semestersRemaining,
            'estimated_graduation_date' => $this->calculateEstimatedGraduationDate($semestersRemaining),
            'on_track' => $semestersRemaining <= $this->getExpectedSemestersRemaining($student),
        ];
    }

    /**
     * Get recommended next units
     */
    protected function getRecommendedNextUnits(Student $student): array
    {
        // This would analyze completed units and recommend next units
        // Implementation depends on your curriculum and prerequisite structure
        return [];
    }

    /**
     * Format academic records
     */
    protected function formatAcademicRecords(Collection $records): array
    {
        return $records->groupBy('semester_id')->map(function ($semesterRecords, $semesterId) {
            $semester = $semesterRecords->first()->semester ?? null;

            return [
                'semester' => [
                    'id' => $semester?->id,
                    'name' => $semester?->name,
                    'code' => $semester?->code,
                ],
                'courses' => $semesterRecords->map(function ($record) {
                    return [
                        'unit_code' => $record->unit?->code,
                        'unit_name' => $record->unit?->name,
                        'credit_hours' => $record->credit_hours,
                        'grade' => $record->final_letter_grade,
                        'grade_points' => $record->grade_points,
                        'completion_status' => $record->completion_status,
                        'lecturer' => $record->courseOffering?->lecturer?->full_name,
                    ];
                })->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Calculate semester summary
     */
    protected function calculateSemesterSummary(Collection $records): array
    {
        return $records->groupBy('semester_id')->map(function ($semesterRecords) {
            $semester = $semesterRecords->first()->semester ?? null;
            $completedRecords = $semesterRecords->where('completion_status', 'completed');

            $totalCredits = $semesterRecords->sum('credit_hours');
            $earnedCredits = $completedRecords->sum('credit_hours_earned');
            $qualityPoints = $completedRecords->sum('quality_points');

            return [
                'semester_name' => $semester?->name,
                'total_courses' => $semesterRecords->count(),
                'completed_courses' => $completedRecords->count(),
                'total_credits' => $totalCredits,
                'earned_credits' => $earnedCredits,
                'semester_gpa' => $earnedCredits > 0 ? round($qualityPoints / $earnedCredits, 2) : 0,
            ];
        })->values()->toArray();
    }

    /**
     * Get GPA history
     */
    protected function getGPAHistory(Student $student): array
    {
        return $student->gpaCalculations()
            ->where('calculation_type', 'semester')
            ->with('semester')
            ->orderBy('created_at')
            ->get()
            ->map(function ($calculation) {
                return [
                    'semester' => $calculation->semester?->name,
                    'gpa' => round($calculation->gpa, 2),
                    'credit_hours' => $calculation->credit_hours_earned,
                    'academic_standing' => $calculation->academic_standing,
                ];
            })
            ->toArray();
    }

    /**
     * Get credit progression
     */
    protected function getCreditProgression(Student $student): array
    {
        $records = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->with('semester')
            ->orderBy('completion_date')
            ->get();

        $progression = [];
        $cumulativeCredits = 0;

        foreach ($records->groupBy('semester_id') as $semesterRecords) {
            $semester = $semesterRecords->first()->semester ?? null;
            $semesterCredits = $semesterRecords->sum('credit_hours_earned');
            $cumulativeCredits += $semesterCredits;

            $progression[] = [
                'semester' => $semester?->name,
                'semester_credits' => $semesterCredits,
                'cumulative_credits' => $cumulativeCredits,
            ];
        }

        return $progression;
    }

    /**
     * Get academic achievements
     */
    protected function getAcademicAchievements(Student $student): array
    {
        $achievements = [];

        // Dean's List achievements
        $deansList = $student->gpaCalculations()
            ->where('gpa', '>=', 3.7)
            ->where('calculation_type', 'semester')
            ->with('semester')
            ->get();

        foreach ($deansList as $achievement) {
            $achievements[] = [
                'type' => 'deans_list',
                'title' => 'Dean\'s List',
                'description' => 'Achieved GPA of '.round($achievement->gpa, 2),
                'semester' => $achievement->semester?->name,
                'date' => $achievement->created_at?->toDateString(),
            ];
        }

        return $achievements;
    }

    /**
     * Get current semester credits
     */
    protected function getCurrentSemesterCredits(Student $student): int
    {
        $currentSemester = $this->resolveCurrentSemester();

        if (! $currentSemester) {
            return 0;
        }

        return $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->where('registration_status', 'registered')
            ->with('courseOffering')
            ->get()
            ->sum('courseOffering.credit_hours');
    }

    /**
     * Get academic standing
     */
    protected function getAcademicStanding(Student $student): string
    {
        $latestGPA = $student->gpaCalculations()
            ->where('calculation_type', 'cumulative')
            ->latest()
            ->first();

        if (! $latestGPA) {
            return 'Good Standing';
        }

        return match (true) {
            $latestGPA->gpa >= 3.7 => 'Dean\'s List',
            $latestGPA->gpa >= 3.5 => 'High Honors',
            $latestGPA->gpa >= 3.0 => 'Good Standing',
            $latestGPA->gpa >= 2.0 => 'Satisfactory Standing',
            default => 'Academic Probation',
        };
    }

    /**
     * Calculate estimated graduation date
     */
    protected function calculateEstimatedGraduationDate(int $semestersRemaining): ?string
    {
        if ($semestersRemaining <= 0) {
            return null;
        }

        // Assuming 2 semesters per year
        $yearsRemaining = (int) ceil($semestersRemaining / 2);

        return now()->addYears($yearsRemaining)->format('Y-m-d');
    }

    /**
     * Get expected semesters remaining
     */
    protected function getExpectedSemestersRemaining(Student $student): int
    {
        if (! $student->expected_graduation_date) {
            return 0;
        }

        $monthsRemaining = now()->diffInMonths($student->expected_graduation_date);

        return (int) ceil($monthsRemaining / 6); // Assuming 6 months per semester
    }

    /**
     * Resolve current semester from system state
     */
    protected function resolveCurrentSemester(): ?Semester
    {
        // Prefer explicitly active semester if set
        $currentPeriodId = app(AcademicPeriodReader::class)->current()?->id;
        $active = $currentPeriodId === null ? null : Semester::find($currentPeriodId);
        if ($active) {
            return $active;
        }

        // Fallback: pick semester that wraps current date
        return Semester::query()
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->orderBy('start_date', 'desc')
            ->first();
    }
}
