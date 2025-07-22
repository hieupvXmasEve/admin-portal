<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\Student;
use App\Models\CurriculumUnit;
use App\Models\AcademicRecord;
use Illuminate\Support\Collection;

class CreditProgressService
{
    /**
     * Get comprehensive credit progress for student
     */
    public function getCreditProgress(Student $student): array
    {
        $curriculumUnits = $student->curriculumVersion->curriculumUnits()->with('unit')->get();
        $completedRecords = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->with('unit')
            ->get();

        $progress = $this->calculateProgress($curriculumUnits, $completedRecords);

        return [
            'overall_progress' => $progress['overall'],
            'category_progress' => $progress['by_category'],
            'semester_progress' => $this->getSemesterProgress($student),
            'remaining_requirements' => $progress['remaining'],
            'graduation_readiness' => $this->assessGraduationReadiness($progress),
        ];
    }

    /**
     * Calculate detailed progress breakdown
     */
    protected function calculateProgress(Collection $curriculumUnits, Collection $completedRecords): array
    {
        $totalRequired = $curriculumUnits->sum('credit_hours');
        $totalCompleted = $completedRecords->sum('credit_hours_earned');

        // Group by curriculum unit type
        $progressByCategory = $curriculumUnits->groupBy('curriculum_unit_type_id')
            ->map(function ($units, $typeId) use ($completedRecords) {
                $requiredCredits = $units->sum('credit_hours');
                $unitCodes = $units->pluck('unit.code')->toArray();
                
                $completedCredits = $completedRecords
                    ->whereIn('unit.code', $unitCodes)
                    ->sum('credit_hours_earned');

                $completionPercentage = $requiredCredits > 0 
                    ? round(($completedCredits / $requiredCredits) * 100, 1)
                    : 0;

                return [
                    'type_id' => $typeId,
                    'type_name' => $units->first()->curriculumUnitType->name ?? 'Unknown',
                    'required_credits' => $requiredCredits,
                    'completed_credits' => $completedCredits,
                    'remaining_credits' => max(0, $requiredCredits - $completedCredits),
                    'completion_percentage' => $completionPercentage,
                    'is_complete' => $completedCredits >= $requiredCredits,
                ];
            });

        // Calculate remaining requirements
        $remaining = $curriculumUnits->filter(function ($unit) use ($completedRecords) {
            return !$completedRecords->contains('unit.code', $unit->unit->code);
        })->map(function ($unit) {
            return [
                'unit_code' => $unit->unit->code,
                'unit_name' => $unit->unit->name,
                'credit_hours' => $unit->credit_hours,
                'semester_offered' => $unit->semester_offered,
                'is_prerequisite_met' => $this->checkPrerequisites($unit),
            ];
        });

        return [
            'overall' => [
                'total_required' => $totalRequired,
                'total_completed' => $totalCompleted,
                'total_remaining' => max(0, $totalRequired - $totalCompleted),
                'completion_percentage' => $totalRequired > 0 
                    ? round(($totalCompleted / $totalRequired) * 100, 1) 
                    : 0,
            ],
            'by_category' => $progressByCategory->values(),
            'remaining' => $remaining->values(),
        ];
    }

    /**
     * Get progress by semester
     */
    protected function getSemesterProgress(Student $student): array
    {
        $semesterProgress = $student->academicRecords()
            ->with(['semester', 'unit'])
            ->get()
            ->groupBy('semester_id')
            ->map(function ($records, $semesterId) {
                $semester = $records->first()->semester;
                $totalCredits = $records->sum('credit_hours');
                $earnedCredits = $records->where('completion_status', 'completed')
                    ->sum('credit_hours_earned');

                return [
                    'semester_id' => $semesterId,
                    'semester_name' => $semester->name,
                    'semester_code' => $semester->code,
                    'total_credits_attempted' => $totalCredits,
                    'credits_earned' => $earnedCredits,
                    'courses_completed' => $records->where('completion_status', 'completed')->count(),
                    'courses_failed' => $records->where('completion_status', 'failed')->count(),
                    'success_rate' => $records->count() > 0 
                        ? round(($records->where('completion_status', 'completed')->count() / $records->count()) * 100, 1)
                        : 0,
                ];
            })
            ->sortBy('semester_name')
            ->values();

        return $semesterProgress->toArray();
    }

    /**
     * Check if prerequisites are met for a curriculum unit
     */
    protected function checkPrerequisites(CurriculumUnit $unit): bool
    {
        // This would need to be implemented based on your prerequisite system
        // For now, return true as a placeholder
        return true;
    }

    /**
     * Assess graduation readiness
     */
    protected function assessGraduationReadiness(array $progress): array
    {
        $overall = $progress['overall'];
        $categoryProgress = collect($progress['by_category']);

        $allCategoriesComplete = $categoryProgress->every('is_complete');
        $overallComplete = $overall['completion_percentage'] >= 100;

        $readinessScore = min(100, $overall['completion_percentage']);

        $blockers = [];
        
        if (!$overallComplete) {
            $blockers[] = "Need {$overall['total_remaining']} more credits";
        }

        $incompleteCategories = $categoryProgress->where('is_complete', false);
        foreach ($incompleteCategories as $category) {
            $blockers[] = "Need {$category['remaining_credits']} more {$category['type_name']} credits";
        }

        return [
            'is_ready' => $allCategoriesComplete && $overallComplete,
            'readiness_score' => $readinessScore,
            'completion_status' => $this->getCompletionStatus($readinessScore),
            'blockers' => $blockers,
            'estimated_semesters_remaining' => $this->estimateRemainingSemesters($overall['total_remaining']),
        ];
    }

    /**
     * Get completion status description
     */
    protected function getCompletionStatus(float $percentage): string
    {
        return match (true) {
            $percentage >= 100 => 'ready_to_graduate',
            $percentage >= 90 => 'near_completion',
            $percentage >= 75 => 'on_track',
            $percentage >= 50 => 'progressing',
            $percentage >= 25 => 'early_stage',
            default => 'just_started',
        };
    }

    /**
     * Estimate remaining semesters based on remaining credits
     */
    protected function estimateRemainingSemesters(float $remainingCredits): int
    {
        // Assume average of 15 credits per semester
        $averageCreditsPerSemester = 15;
        
        if ($remainingCredits <= 0) {
            return 0;
        }

        return (int) ceil($remainingCredits / $averageCreditsPerSemester);
    }

    /**
     * Get credit requirements by type
     */
    public function getCreditRequirementsByType(Student $student): array
    {
        return $student->curriculumVersion->curriculumUnits()
            ->with(['curriculumUnitType', 'unit'])
            ->get()
            ->groupBy('curriculum_unit_type_id')
            ->map(function ($units, $typeId) {
                $type = $units->first()->curriculumUnitType;
                
                return [
                    'type_id' => $typeId,
                    'type_name' => $type->name,
                    'type_description' => $type->description,
                    'required_credits' => $units->sum('credit_hours'),
                    'unit_count' => $units->count(),
                    'units' => $units->map(function ($unit) {
                        return [
                            'code' => $unit->unit->code,
                            'name' => $unit->unit->name,
                            'credits' => $unit->credit_hours,
                            'semester_offered' => $unit->semester_offered,
                        ];
                    }),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get transfer credit summary
     */
    public function getTransferCreditSummary(Student $student): array
    {
        $transferRecords = $student->academicRecords()
            ->where('is_transfer_credit', true)
            ->with('unit')
            ->get();

        if ($transferRecords->isEmpty()) {
            return [
                'has_transfer_credits' => false,
                'total_transfer_credits' => 0,
                'transfer_records' => [],
            ];
        }

        return [
            'has_transfer_credits' => true,
            'total_transfer_credits' => $transferRecords->sum('credit_hours_earned'),
            'transfer_institutions' => $transferRecords->pluck('transfer_institution')->unique()->values(),
            'transfer_records' => $transferRecords->map(function ($record) {
                return [
                    'unit_code' => $record->unit->code,
                    'unit_name' => $record->unit->name,
                    'credits' => $record->credit_hours_earned,
                    'grade' => $record->final_letter_grade,
                    'transfer_institution' => $record->transfer_institution,
                    'transfer_course_code' => $record->transfer_course_code,
                ];
            }),
        ];
    }
}
