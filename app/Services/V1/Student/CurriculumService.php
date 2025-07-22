<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\Student;
use App\Models\CurriculumUnit;
use App\Models\Unit;
use App\Models\Prerequisite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CurriculumService
{
    /**
     * Get curriculum overview for student
     */
    public function getCurriculumOverview(Student $student): array
    {
        $cacheKey = "curriculum:overview:student:{$student->id}";
        
        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $curriculumVersion = $student->curriculumVersion;
            $curriculumUnits = $this->getCurriculumUnits($student);
            $completedUnits = $this->getCompletedUnits($student);
            $currentEnrollments = $this->getCurrentEnrollments($student);

            return [
                'curriculum_info' => [
                    'id' => $curriculumVersion->id,
                    'version' => $curriculumVersion->version,
                    'effective_date' => $curriculumVersion->effective_date->toDateString(),
                    'total_credit_hours' => $curriculumVersion->total_credit_hours,
                    'program' => [
                        'id' => $student->program->id,
                        'name' => $student->program->name,
                        'code' => $student->program->code,
                        'degree_type' => $student->program->degree_type,
                    ],
                ],
                'curriculum_structure' => $this->organizeCurriculumStructure($curriculumUnits),
                'progress_summary' => $this->calculateProgressSummary($curriculumUnits, $completedUnits, $currentEnrollments),
                'completion_status' => $this->calculateCompletionStatus($curriculumUnits, $completedUnits),
            ];
        });
    }

    /**
     * Get prerequisite tree for curriculum
     */
    public function getPrerequisiteTree(Student $student): array
    {
        $cacheKey = "curriculum:prerequisite_tree:student:{$student->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($student) {
            $curriculumUnits = $this->getCurriculumUnits($student);
            $completedUnits = $this->getCompletedUnits($student);
            
            return [
                'prerequisite_tree' => $this->buildPrerequisiteTree($curriculumUnits, $completedUnits),
                'prerequisite_chains' => $this->identifyPrerequisiteChains($curriculumUnits),
                'available_units' => $this->getAvailableUnits($student),
                'blocked_units' => $this->getBlockedUnits($student),
            ];
        });
    }

    /**
     * Get program requirements breakdown
     */
    public function getProgramRequirements(Student $student): array
    {
        $cacheKey = "curriculum:requirements:student:{$student->id}";
        
        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $curriculumUnits = $this->getCurriculumUnits($student);
            $completedUnits = $this->getCompletedUnits($student);

            return [
                'core_requirements' => $this->getCoreRequirements($curriculumUnits, $completedUnits),
                'elective_requirements' => $this->getElectiveRequirements($curriculumUnits, $completedUnits),
                'specialization_requirements' => $this->getSpecializationRequirements($curriculumUnits, $completedUnits),
                'general_education_requirements' => $this->getGeneralEducationRequirements($curriculumUnits, $completedUnits),
                'credit_distribution' => $this->getCreditDistribution($curriculumUnits, $completedUnits),
                'graduation_requirements' => $this->getGraduationRequirements($student),
            ];
        });
    }

    /**
     * Get academic roadmap
     */
    public function getAcademicRoadmap(Student $student): array
    {
        $cacheKey = "curriculum:roadmap:student:{$student->id}";
        
        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $curriculumUnits = $this->getCurriculumUnits($student);
            $completedUnits = $this->getCompletedUnits($student);
            $currentEnrollments = $this->getCurrentEnrollments($student);

            return [
                'roadmap_overview' => $this->generateRoadmapOverview($student),
                'semester_plan' => $this->generateSemesterPlan($student, $curriculumUnits, $completedUnits),
                'recommended_sequence' => $this->getRecommendedSequence($student),
                'alternative_pathways' => $this->getAlternativePathways($student),
                'graduation_timeline' => $this->calculateGraduationTimeline($student, $curriculumUnits, $completedUnits),
            ];
        });
    }

    /**
     * Get curriculum units for student
     */
    protected function getCurriculumUnits(Student $student): Collection
    {
        return CurriculumUnit::where('curriculum_version_id', $student->curriculum_version_id)
            ->with(['unit', 'prerequisites.prerequisiteUnit'])
            ->get();
    }

    /**
     * Get completed units for student
     */
    protected function getCompletedUnits(Student $student): Collection
    {
        return $student->academicRecords()
            ->where('completion_status', 'completed')
            ->with('unit')
            ->get()
            ->pluck('unit');
    }

    /**
     * Get current enrollments
     */
    protected function getCurrentEnrollments(Student $student): Collection
    {
        $currentSemester = $student->getCurrentSemester();
        
        if (!$currentSemester) {
            return collect();
        }

        return $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->where('registration_status', 'registered')
            ->with('courseOffering.curriculumUnit.unit')
            ->get()
            ->pluck('courseOffering.curriculumUnit.unit');
    }

    /**
     * Organize curriculum structure
     */
    protected function organizeCurriculumStructure(Collection $curriculumUnits): array
    {
        return [
            'by_category' => $this->groupByCategory($curriculumUnits),
            'by_year_level' => $this->groupByYearLevel($curriculumUnits),
            'by_semester' => $this->groupBySemester($curriculumUnits),
            'total_units' => $curriculumUnits->count(),
            'total_credit_hours' => $curriculumUnits->sum('credit_hours'),
        ];
    }

    /**
     * Calculate progress summary
     */
    protected function calculateProgressSummary(Collection $curriculumUnits, Collection $completedUnits, Collection $currentEnrollments): array
    {
        $totalUnits = $curriculumUnits->count();
        $completedCount = $completedUnits->count();
        $currentCount = $currentEnrollments->count();
        $remainingCount = $totalUnits - $completedCount - $currentCount;

        $totalCredits = $curriculumUnits->sum('credit_hours');
        $completedCredits = $curriculumUnits->whereIn('unit_id', $completedUnits->pluck('id'))->sum('credit_hours');
        $currentCredits = $curriculumUnits->whereIn('unit_id', $currentEnrollments->pluck('id'))->sum('credit_hours');

        return [
            'units' => [
                'total' => $totalUnits,
                'completed' => $completedCount,
                'current' => $currentCount,
                'remaining' => $remainingCount,
                'completion_percentage' => $totalUnits > 0 ? round(($completedCount / $totalUnits) * 100, 1) : 0,
            ],
            'credits' => [
                'total' => $totalCredits,
                'completed' => $completedCredits,
                'current' => $currentCredits,
                'remaining' => $totalCredits - $completedCredits - $currentCredits,
                'completion_percentage' => $totalCredits > 0 ? round(($completedCredits / $totalCredits) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Calculate completion status by category
     */
    protected function calculateCompletionStatus(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $completedUnitIds = $completedUnits->pluck('id');
        
        return $curriculumUnits->groupBy('unit_category')->map(function ($categoryUnits, $category) use ($completedUnitIds) {
            $totalUnits = $categoryUnits->count();
            $completedCount = $categoryUnits->whereIn('unit_id', $completedUnitIds)->count();
            
            return [
                'category' => $category,
                'total_units' => $totalUnits,
                'completed_units' => $completedCount,
                'remaining_units' => $totalUnits - $completedCount,
                'completion_percentage' => $totalUnits > 0 ? round(($completedCount / $totalUnits) * 100, 1) : 0,
                'is_complete' => $completedCount >= $totalUnits,
            ];
        })->values()->toArray();
    }

    /**
     * Build prerequisite tree
     */
    protected function buildPrerequisiteTree(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $completedUnitIds = $completedUnits->pluck('id');
        $tree = [];

        foreach ($curriculumUnits as $curriculumUnit) {
            $unit = $curriculumUnit->unit;
            $prerequisites = $curriculumUnit->prerequisites;

            $tree[] = [
                'unit' => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_hours' => $curriculumUnit->credit_hours,
                    'category' => $curriculumUnit->unit_category,
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester,
                ],
                'status' => [
                    'is_completed' => $completedUnitIds->contains($unit->id),
                    'is_available' => $this->isUnitAvailable($curriculumUnit, $completedUnitIds),
                    'prerequisites_met' => $this->arePrerequisitesMet($prerequisites, $completedUnitIds),
                ],
                'prerequisites' => $prerequisites->map(function ($prerequisite) use ($completedUnitIds) {
                    return [
                        'unit' => [
                            'id' => $prerequisite->prerequisiteUnit->id,
                            'code' => $prerequisite->prerequisiteUnit->code,
                            'name' => $prerequisite->prerequisiteUnit->name,
                        ],
                        'type' => $prerequisite->prerequisite_type,
                        'is_completed' => $completedUnitIds->contains($prerequisite->prerequisite_unit_id),
                    ];
                })->toArray(),
            ];
        }

        return $tree;
    }

    /**
     * Identify prerequisite chains
     */
    protected function identifyPrerequisiteChains(Collection $curriculumUnits): array
    {
        $chains = [];
        
        foreach ($curriculumUnits as $curriculumUnit) {
            if ($curriculumUnit->prerequisites->isNotEmpty()) {
                $chain = $this->buildPrerequisiteChain($curriculumUnit, $curriculumUnits);
                if (count($chain) > 1) {
                    $chains[] = $chain;
                }
            }
        }

        return $chains;
    }

    /**
     * Get available units (prerequisites met)
     */
    protected function getAvailableUnits(Student $student): array
    {
        $curriculumUnits = $this->getCurriculumUnits($student);
        $completedUnits = $this->getCompletedUnits($student);
        $currentEnrollments = $this->getCurrentEnrollments($student);
        
        $completedUnitIds = $completedUnits->pluck('id');
        $enrolledUnitIds = $currentEnrollments->pluck('id');

        return $curriculumUnits->filter(function ($curriculumUnit) use ($completedUnitIds, $enrolledUnitIds) {
            return !$completedUnitIds->contains($curriculumUnit->unit_id) &&
                   !$enrolledUnitIds->contains($curriculumUnit->unit_id) &&
                   $this->isUnitAvailable($curriculumUnit, $completedUnitIds);
        })->map(function ($curriculumUnit) {
            return [
                'unit' => [
                    'id' => $curriculumUnit->unit->id,
                    'code' => $curriculumUnit->unit->code,
                    'name' => $curriculumUnit->unit->name,
                    'credit_hours' => $curriculumUnit->credit_hours,
                    'category' => $curriculumUnit->unit_category,
                ],
                'recommended_semester' => $curriculumUnit->semester,
                'year_level' => $curriculumUnit->year_level,
            ];
        })->values()->toArray();
    }

    /**
     * Get blocked units (prerequisites not met)
     */
    protected function getBlockedUnits(Student $student): array
    {
        $curriculumUnits = $this->getCurriculumUnits($student);
        $completedUnits = $this->getCompletedUnits($student);
        $currentEnrollments = $this->getCurrentEnrollments($student);
        
        $completedUnitIds = $completedUnits->pluck('id');
        $enrolledUnitIds = $currentEnrollments->pluck('id');

        return $curriculumUnits->filter(function ($curriculumUnit) use ($completedUnitIds, $enrolledUnitIds) {
            return !$completedUnitIds->contains($curriculumUnit->unit_id) &&
                   !$enrolledUnitIds->contains($curriculumUnit->unit_id) &&
                   !$this->isUnitAvailable($curriculumUnit, $completedUnitIds);
        })->map(function ($curriculumUnit) use ($completedUnitIds) {
            $missingPrerequisites = $curriculumUnit->prerequisites->filter(function ($prerequisite) use ($completedUnitIds) {
                return !$completedUnitIds->contains($prerequisite->prerequisite_unit_id);
            });

            return [
                'unit' => [
                    'id' => $curriculumUnit->unit->id,
                    'code' => $curriculumUnit->unit->code,
                    'name' => $curriculumUnit->unit->name,
                    'credit_hours' => $curriculumUnit->credit_hours,
                ],
                'missing_prerequisites' => $missingPrerequisites->map(function ($prerequisite) {
                    return [
                        'unit' => [
                            'id' => $prerequisite->prerequisiteUnit->id,
                            'code' => $prerequisite->prerequisiteUnit->code,
                            'name' => $prerequisite->prerequisiteUnit->name,
                        ],
                        'type' => $prerequisite->prerequisite_type,
                    ];
                })->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Get core requirements
     */
    protected function getCoreRequirements(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $coreUnits = $curriculumUnits->where('unit_category', 'core');
        $completedUnitIds = $completedUnits->pluck('id');

        return [
            'total_units' => $coreUnits->count(),
            'completed_units' => $coreUnits->whereIn('unit_id', $completedUnitIds)->count(),
            'total_credits' => $coreUnits->sum('credit_hours'),
            'completed_credits' => $coreUnits->whereIn('unit_id', $completedUnitIds)->sum('credit_hours'),
            'units' => $coreUnits->map(function ($curriculumUnit) use ($completedUnitIds) {
                return [
                    'unit' => [
                        'id' => $curriculumUnit->unit->id,
                        'code' => $curriculumUnit->unit->code,
                        'name' => $curriculumUnit->unit->name,
                        'credit_hours' => $curriculumUnit->credit_hours,
                    ],
                    'is_completed' => $completedUnitIds->contains($curriculumUnit->unit_id),
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get elective requirements
     */
    protected function getElectiveRequirements(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $electiveUnits = $curriculumUnits->where('unit_category', 'elective');
        $completedUnitIds = $completedUnits->pluck('id');

        return [
            'total_units' => $electiveUnits->count(),
            'completed_units' => $electiveUnits->whereIn('unit_id', $completedUnitIds)->count(),
            'total_credits' => $electiveUnits->sum('credit_hours'),
            'completed_credits' => $electiveUnits->whereIn('unit_id', $completedUnitIds)->sum('credit_hours'),
            'units' => $electiveUnits->map(function ($curriculumUnit) use ($completedUnitIds) {
                return [
                    'unit' => [
                        'id' => $curriculumUnit->unit->id,
                        'code' => $curriculumUnit->unit->code,
                        'name' => $curriculumUnit->unit->name,
                        'credit_hours' => $curriculumUnit->credit_hours,
                    ],
                    'is_completed' => $completedUnitIds->contains($curriculumUnit->unit_id),
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get specialization requirements
     */
    protected function getSpecializationRequirements(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $specializationUnits = $curriculumUnits->where('unit_category', 'specialization');
        $completedUnitIds = $completedUnits->pluck('id');

        return [
            'total_units' => $specializationUnits->count(),
            'completed_units' => $specializationUnits->whereIn('unit_id', $completedUnitIds)->count(),
            'total_credits' => $specializationUnits->sum('credit_hours'),
            'completed_credits' => $specializationUnits->whereIn('unit_id', $completedUnitIds)->sum('credit_hours'),
            'units' => $specializationUnits->map(function ($curriculumUnit) use ($completedUnitIds) {
                return [
                    'unit' => [
                        'id' => $curriculumUnit->unit->id,
                        'code' => $curriculumUnit->unit->code,
                        'name' => $curriculumUnit->unit->name,
                        'credit_hours' => $curriculumUnit->credit_hours,
                    ],
                    'is_completed' => $completedUnitIds->contains($curriculumUnit->unit_id),
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get general education requirements
     */
    protected function getGeneralEducationRequirements(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $genEdUnits = $curriculumUnits->where('unit_category', 'general_education');
        $completedUnitIds = $completedUnits->pluck('id');

        return [
            'total_units' => $genEdUnits->count(),
            'completed_units' => $genEdUnits->whereIn('unit_id', $completedUnitIds)->count(),
            'total_credits' => $genEdUnits->sum('credit_hours'),
            'completed_credits' => $genEdUnits->whereIn('unit_id', $completedUnitIds)->sum('credit_hours'),
            'units' => $genEdUnits->map(function ($curriculumUnit) use ($completedUnitIds) {
                return [
                    'unit' => [
                        'id' => $curriculumUnit->unit->id,
                        'code' => $curriculumUnit->unit->code,
                        'name' => $curriculumUnit->unit->name,
                        'credit_hours' => $curriculumUnit->credit_hours,
                    ],
                    'is_completed' => $completedUnitIds->contains($curriculumUnit->unit_id),
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get credit distribution
     */
    protected function getCreditDistribution(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $completedUnitIds = $completedUnits->pluck('id');
        
        return $curriculumUnits->groupBy('unit_category')->map(function ($categoryUnits, $category) use ($completedUnitIds) {
            $totalCredits = $categoryUnits->sum('credit_hours');
            $completedCredits = $categoryUnits->whereIn('unit_id', $completedUnitIds)->sum('credit_hours');
            
            return [
                'category' => $category,
                'total_credits' => $totalCredits,
                'completed_credits' => $completedCredits,
                'remaining_credits' => $totalCredits - $completedCredits,
                'completion_percentage' => $totalCredits > 0 ? round(($completedCredits / $totalCredits) * 100, 1) : 0,
            ];
        })->values()->toArray();
    }

    /**
     * Get graduation requirements
     */
    protected function getGraduationRequirements(Student $student): array
    {
        $curriculumVersion = $student->curriculumVersion;
        $completedCredits = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->sum('credit_hours_earned');

        return [
            'total_credits_required' => $curriculumVersion->total_credit_hours,
            'credits_completed' => $completedCredits,
            'credits_remaining' => max(0, $curriculumVersion->total_credit_hours - $completedCredits),
            'minimum_gpa_required' => $curriculumVersion->minimum_gpa ?? 2.0,
            'current_gpa' => $this->getCurrentGPA($student),
            'gpa_requirement_met' => $this->getCurrentGPA($student) >= ($curriculumVersion->minimum_gpa ?? 2.0),
            'additional_requirements' => [
                'residency_requirement' => 'Complete at least 50% of credits at this institution',
                'capstone_requirement' => 'Complete capstone project or thesis',
                'internship_requirement' => 'Complete required internship hours',
            ],
        ];
    }

    /**
     * Generate roadmap overview
     */
    protected function generateRoadmapOverview(Student $student): array
    {
        $enrollmentDate = $student->enrollment_date;
        $expectedGraduation = $student->expected_graduation_date;
        $programDuration = $student->program->duration_years;

        return [
            'enrollment_date' => $enrollmentDate?->toDateString(),
            'expected_graduation_date' => $expectedGraduation?->toDateString(),
            'program_duration_years' => $programDuration,
            'current_year' => $this->calculateCurrentYear($student),
            'semesters_completed' => $this->calculateSemestersCompleted($student),
            'semesters_remaining' => $this->calculateSemestersRemaining($student),
            'on_track_for_graduation' => $this->isOnTrackForGraduation($student),
        ];
    }

    /**
     * Generate semester plan
     */
    protected function generateSemesterPlan(Student $student, Collection $curriculumUnits, Collection $completedUnits): array
    {
        // This would generate a recommended semester-by-semester plan
        // Implementation would depend on your specific curriculum structure
        return [];
    }

    /**
     * Get recommended sequence
     */
    protected function getRecommendedSequence(Student $student): array
    {
        // This would provide the recommended order for taking units
        // Implementation would depend on your curriculum design
        return [];
    }

    /**
     * Get alternative pathways
     */
    protected function getAlternativePathways(Student $student): array
    {
        // This would identify alternative ways to complete the program
        // Implementation would depend on your curriculum flexibility
        return [];
    }

    /**
     * Calculate graduation timeline
     */
    protected function calculateGraduationTimeline(Student $student, Collection $curriculumUnits, Collection $completedUnits): array
    {
        $totalCreditsRequired = $student->curriculumVersion->total_credit_hours;
        $creditsCompleted = $completedUnits->sum(function ($unit) use ($curriculumUnits) {
            return $curriculumUnits->where('unit_id', $unit->id)->first()?->credit_hours ?? 0;
        });
        
        $creditsRemaining = $totalCreditsRequired - $creditsCompleted;
        $averageCreditsPerSemester = 18; // Typical full-time load
        
        $semestersRemaining = $creditsRemaining > 0 
            ? ceil($creditsRemaining / $averageCreditsPerSemester)
            : 0;

        return [
            'credits_remaining' => $creditsRemaining,
            'semesters_remaining' => $semestersRemaining,
            'estimated_graduation_date' => $this->calculateEstimatedGraduationDate($semestersRemaining),
            'on_track' => $semestersRemaining <= $this->getExpectedSemestersRemaining($student),
            'acceleration_options' => $this->getAccelerationOptions($student),
        ];
    }

    /**
     * Helper methods
     */
    protected function groupByCategory(Collection $curriculumUnits): array
    {
        return $curriculumUnits->groupBy('unit_category')->map->count()->toArray();
    }

    protected function groupByYearLevel(Collection $curriculumUnits): array
    {
        return $curriculumUnits->groupBy('year_level')->map->count()->toArray();
    }

    protected function groupBySemester(Collection $curriculumUnits): array
    {
        return $curriculumUnits->groupBy('semester')->map->count()->toArray();
    }

    protected function isUnitAvailable(CurriculumUnit $curriculumUnit, Collection $completedUnitIds): bool
    {
        return $this->arePrerequisitesMet($curriculumUnit->prerequisites, $completedUnitIds);
    }

    protected function arePrerequisitesMet(Collection $prerequisites, Collection $completedUnitIds): bool
    {
        foreach ($prerequisites as $prerequisite) {
            if (!$completedUnitIds->contains($prerequisite->prerequisite_unit_id)) {
                return false;
            }
        }
        return true;
    }

    protected function buildPrerequisiteChain(CurriculumUnit $curriculumUnit, Collection $allUnits): array
    {
        $chain = [$curriculumUnit->unit->code];
        
        foreach ($curriculumUnit->prerequisites as $prerequisite) {
            $prereqUnit = $allUnits->where('unit_id', $prerequisite->prerequisite_unit_id)->first();
            if ($prereqUnit) {
                $subChain = $this->buildPrerequisiteChain($prereqUnit, $allUnits);
                $chain = array_merge($subChain, $chain);
            }
        }
        
        return array_unique($chain);
    }

    protected function getCurrentGPA(Student $student): float
    {
        $latestGPA = $student->gpaCalculations()
            ->where('calculation_type', 'cumulative')
            ->latest()
            ->first();

        return $latestGPA ? round($latestGPA->gpa, 2) : 0.0;
    }

    protected function calculateCurrentYear(Student $student): int
    {
        if (!$student->enrollment_date) {
            return 1;
        }

        $yearsEnrolled = $student->enrollment_date->diffInYears(now()) + 1;
        return min($yearsEnrolled, $student->program->duration_years);
    }

    protected function calculateSemestersCompleted(Student $student): int
    {
        return $student->academicRecords()
            ->select('semester_id')
            ->distinct()
            ->count();
    }

    protected function calculateSemestersRemaining(Student $student): int
    {
        $totalSemesters = $student->program->duration_years * 2; // Assuming 2 semesters per year
        $completedSemesters = $this->calculateSemestersCompleted($student);
        
        return max(0, $totalSemesters - $completedSemesters);
    }

    protected function isOnTrackForGraduation(Student $student): bool
    {
        $expectedSemestersCompleted = $this->calculateExpectedSemestersCompleted($student);
        $actualSemestersCompleted = $this->calculateSemestersCompleted($student);
        
        return $actualSemestersCompleted >= $expectedSemestersCompleted;
    }

    protected function calculateExpectedSemestersCompleted(Student $student): int
    {
        if (!$student->enrollment_date) {
            return 0;
        }

        $monthsEnrolled = $student->enrollment_date->diffInMonths(now());
        return floor($monthsEnrolled / 6); // Assuming 6 months per semester
    }

    protected function calculateEstimatedGraduationDate(int $semestersRemaining): ?string
    {
        if ($semestersRemaining <= 0) {
            return null;
        }

        $monthsRemaining = $semestersRemaining * 6; // 6 months per semester
        return now()->addMonths($monthsRemaining)->format('Y-m-d');
    }

    protected function getExpectedSemestersRemaining(Student $student): int
    {
        if (!$student->expected_graduation_date) {
            return 0;
        }

        $monthsRemaining = now()->diffInMonths($student->expected_graduation_date);
        return ceil($monthsRemaining / 6);
    }

    protected function getAccelerationOptions(Student $student): array
    {
        return [
            'summer_sessions' => 'Take additional units during summer sessions',
            'overload_semesters' => 'Take more than the standard credit load',
            'credit_transfer' => 'Transfer credits from other institutions',
            'prior_learning_assessment' => 'Get credit for prior learning and experience',
        ];
    }
}
