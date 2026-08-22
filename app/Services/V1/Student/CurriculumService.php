<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\CurriculumUnit;
use App\Models\Student;
use App\Models\Unit;
use App\Models\UnitPrerequisiteCondition;
use App\Models\UnitPrerequisiteGroup;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
            $program = $student->program;
            Log::info('$curriculumVersion', [
                'curriculumVersion' => $curriculumVersion->version_code,
            ]);
            // If essential relations are missing, return a safe default structure
            if (! $curriculumVersion || ! $program) {
                return [
                    'curriculum_info' => [
                        'id' => $curriculumVersion->id ?? null,
                        'version' => $curriculumVersion->version_code ?? 'N/A',
                        'effective_date' => ($curriculumVersion && $curriculumVersion->effective_date)
                            ? $curriculumVersion->effective_date->toDateString()
                            : null,
                        'total_credit_hours' => $curriculumVersion->total_credit_hours ?? 0,
                        'program' => [
                            'id' => $program->id ?? null,
                            'name' => $program->name ?? '',
                            'code' => $program->code ?? '',
                            'degree_type' => $program->degree_type ?? 'unknown',
                        ],
                    ],
                    'curriculum_structure' => [
                        'by_category' => [],
                        'by_year_level' => [],
                        'by_semester' => [],
                        'total_units' => 0,
                        'total_credit_hours' => 0,
                    ],
                    'progress_summary' => [
                        'units' => [
                            'total' => 0,
                            'completed' => 0,
                            'current' => 0,
                            'remaining' => 0,
                            'completion_percentage' => 0,
                        ],
                        'credits' => [
                            'total' => 0,
                            'completed' => 0,
                            'current' => 0,
                            'remaining' => 0,
                            'completion_percentage' => 0,
                        ],
                    ],
                    'completion_status' => [],
                ];
            }

            $curriculumUnits = $this->getCurriculumUnits($student);
            $completedUnits = $this->getCompletedUnits($student);
            $currentEnrollments = $this->getCurrentEnrollments($student);

            return [
                'curriculum_info' => [
                    'id' => $curriculumVersion->id,
                    'version' => $curriculumVersion->version_code,
                    'effective_date' => $curriculumVersion->effective_date?->toDateString(),
                    'total_credit_hours' => $curriculumVersion->total_credit_hours,
                    'program' => [
                        'id' => $program->id,
                        'name' => $program->name,
                        'code' => $program->code,
                        'degree_type' => $program->degree_type ?? 'unknown',
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
    /**
     * Get academic roadmap with student progress
     */
    public function getAcademicRoadmap(Student $student): array
    {
        $cacheKey = "curriculum:roadmap:student:{$student->id}";

        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $curriculumVersion = $student->curriculumVersion;

            if (! $curriculumVersion) {
                return [];
            }

            // Eager load necessary data for roadmap visualization
            $curriculumVersion->load([
                'program:id,name,code',
                'specialization:id,name,code',
                'effectiveFromSemester:id,name,code',
                'curriculumUnits' => function ($query) {
                    $query->orderBy('year_level')
                        ->orderBy('semester_number')
                        ->orderBy('id');
                },
                'curriculumUnits.unit:id,code,name,credit_points',
                'curriculumUnits.unit.prerequisiteGroups.conditions.requiredUnit:id,code',
            ]);

            // Get passed unit IDs based on academic records
            $passedUnitIds = TranscriptEntry::query()
                ->where('student_id', $student->id)
                ->where('is_passed', true)
                ->pluck('unit_id')
                ->unique();

            return [
                'id' => $curriculumVersion->id,
                'version_code' => $curriculumVersion->version_code,
                'program' => $curriculumVersion->program,
                'specialization' => $curriculumVersion->specialization,
                'effective_from_semester' => $curriculumVersion->effectiveFromSemester,
                'curriculum_units' => $curriculumVersion->curriculumUnits->map(function ($cu) use ($passedUnitIds) {
                    return [
                        'id' => $cu->id,
                        'unit_id' => $cu->unit_id,
                        'semester_number' => $cu->semester_number,
                        'year_level' => $cu->year_level,
                        // Determine status based on passed units
                        'status' => $passedUnitIds->contains($cu->unit_id) ? 'completed' : 'pending',
                        'unit' => $cu->unit ? [
                            'id' => $cu->unit->id,
                            'code' => $cu->unit->code,
                            'name' => $cu->unit->name,
                            'credit_points' => $cu->unit->credit_points,
                            'prerequisite_groups' => $cu->unit->prerequisiteGroups->map(function ($group) {
                                return [
                                    'logic_operator' => $group->logic_operator,
                                    'conditions' => $group->conditions->map(function ($condition) {
                                        return [
                                            'type' => $condition->type,
                                            'required_unit_id' => $condition->required_unit_id,
                                            'required_unit_code' => $condition->requiredUnit?->code,
                                        ];
                                    }),
                                ];
                            }),
                        ] : null,
                    ];
                }),
            ];
        });
    }

    /**
     * Get curriculum organized by semester with grades and status
     */
    public function getCurriculumBySemester(Student $student): array
    {
        $cacheKey = "curriculum:by_semester:student:{$student->id}";

        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $curriculumUnits = $this->getCurriculumUnitsWithRelations($student);
            $academicRecords = $this->getAcademicRecords($student);
            $courseRegistrations = $this->getCourseRegistrations($student);

            return $this->organizeBySemester($curriculumUnits, $academicRecords, $courseRegistrations);
        });
    }

    /**
     * Get curriculum units for student
     */
    protected function getCurriculumUnits(Student $student): Collection
    {
        return CurriculumUnit::where('curriculum_version_id', $student->curriculum_version_id)
            ->with(['unit'])
            ->get();
    }

    /**
     * Get completed units for student
     */
    protected function getCompletedUnits(Student $student): Collection
    {
        // For now, return empty collection to avoid relationship issues
        // TODO: Fix academic records relationship
        return collect();
    }

    /**
     * Get current enrollments
     */
    protected function getCurrentEnrollments(Student $student): Collection
    {
        // For now, return empty collection to avoid relationship issues
        // TODO: Fix course registrations relationship
        return collect();
    }

    /**
     * Get curriculum units with all necessary relations
     */
    protected function getCurriculumUnitsWithRelations(Student $student): Collection
    {
        return CurriculumUnit::where('curriculum_version_id', $student->curriculum_version_id)
            ->with([
                'unit',
                'semester',
            ])
            ->orderBy('year_level')
            ->orderBy('semester_number')
            ->get();
    }

    /**
     * Get academic records for student
     */
    protected function getAcademicRecords(Student $student): Collection
    {
        return TranscriptEntry::query()
            ->where('student_id', $student->id)
            ->with(['unit', 'semester', 'courseOffering'])
            ->get();
    }

    /**
     * Get course registrations for student
     */
    protected function getCourseRegistrations(Student $student): Collection
    {
        return $student->courseRegistrations()
            ->with([
                'courseOffering.unit',
                'semester',
            ])
            ->get();
    }

    /**
     * Organize curriculum units by semester with grades and status
     */
    protected function organizeBySemester(
        Collection $curriculumUnits,
        Collection $academicRecords,
        Collection $courseRegistrations
    ): array {
        // Create lookup arrays for efficient access
        // Get the latest academic record for each unit (highest attempt_number, then latest completion_date)
        $academicRecordsByUnit = $academicRecords->groupBy('unit_id')->map(function ($records) {
            return $records->sortByDesc('attempt_number')
                ->sortByDesc('completion_date')
                ->sortByDesc('enrollment_date')
                ->first();
        });
        $registrationsByUnit = $courseRegistrations->groupBy(function ($registration) {
            return $registration->courseOffering?->unit_id;
        });

        // Group curriculum units by year and semester
        $semesterGroups = $curriculumUnits->groupBy(function ($curriculumUnit) {
            return "Year {$curriculumUnit->year_level} - Semester {$curriculumUnit->semester_number}";
        });

        $result = [];

        foreach ($semesterGroups as $semesterKey => $semesterUnits) {
            // Extract year and semester from key
            preg_match('/Year (\d+) - Semester (\d+)/', $semesterKey, $matches);
            $yearLevel = (int) ($matches[1] ?? 1);
            $semesterNumber = (int) ($matches[2] ?? 1);

            $subjects = $semesterUnits->map(function ($curriculumUnit) use ($academicRecordsByUnit, $registrationsByUnit) {
                $unit = $curriculumUnit->unit;
                $unitId = $unit->id;

                // Get academic record (grades) for this unit
                $academicRecord = $academicRecordsByUnit->get($unitId);

                // Get course registrations for this unit (to determine study status)
                $unitRegistrations = $registrationsByUnit->get($unitId, collect());

                // Determine study status
                $studyStatus = $this->determineStudyStatus($academicRecord, $unitRegistrations);

                // Get grade information
                $gradeInfo = $this->getGradeInfo($academicRecord);

                // Check if student has registered for classes (to prioritize ordering)
                $hasRegistration = $unitRegistrations->isNotEmpty();

                return [
                    'curriculum_unit' => [
                        'id' => $curriculumUnit->id,
                        'year_level' => $curriculumUnit->year_level,
                        'semester_number' => $curriculumUnit->semester_number,
                        'unit_scope' => $curriculumUnit->unit_scope,
                        'note' => $curriculumUnit->note,
                    ],
                    'unit' => [
                        'id' => $unit->id,
                        'code' => $unit->code,
                        'name' => $unit->name,
                        'credit_points' => $unit->credit_points,
                    ],
                    'study_status' => $studyStatus,
                    'grade_info' => $gradeInfo,
                    'has_registration' => $hasRegistration,
                    'registrations' => $unitRegistrations->map(function ($registration) {
                        return [
                            'id' => $registration->id,
                            'status' => $registration->registration_status,
                            'registration_date' => $registration->registration_date?->toDateString(),
                            'semester' => [
                                'id' => $registration->semester?->id,
                                'name' => $registration->semester?->name,
                                'code' => $registration->semester?->code,
                            ],
                        ];
                    })->values()->toArray(),
                ];
            })
                // Sort by: 1) has registration first, 2) then by unit code
                ->sortBy([
                    fn ($subject) => ! $subject['has_registration'], // Registered subjects first
                    fn ($subject) => $subject['unit']['code'], // Then alphabetically by code
                ])
                ->values()
                ->toArray();

            $result[] = [
                'semester_info' => [
                    'year_level' => $yearLevel,
                    'semester_number' => $semesterNumber,
                    'display_name' => $semesterKey,
                ],
                'subjects' => $subjects,
                'summary' => [
                    'total_subjects' => count($subjects),
                    'total_credit_points' => array_sum(array_column(array_column($subjects, 'unit'), 'credit_points')),
                    'completed_subjects' => count(array_filter($subjects, function ($s) {
                        return $s['study_status']['status'] === 'completed';
                    })),
                    'current_subjects' => count(array_filter($subjects, function ($s) {
                        return in_array($s['study_status']['status'], ['in_progress', 'registered']);
                    })),
                    'failed_subjects' => count(array_filter($subjects, function ($s) {
                        return $s['study_status']['status'] === 'failed';
                    })),
                    'retaking_subjects' => count(array_filter($subjects, function ($s) {
                        return $s['study_status']['status'] === 'retaking';
                    })),
                    'withdrawn_subjects' => count(array_filter($subjects, function ($s) {
                        return $s['study_status']['status'] === 'withdrawn';
                    })),
                    'not_started_subjects' => count(array_filter($subjects, function ($s) {
                        return $s['study_status']['status'] === 'not_started';
                    })),
                    'registered_subjects' => count(array_filter($subjects, function ($s) {
                        return $s['has_registration'];
                    })),
                ],
            ];
        }

        // Sort semesters by year and semester number
        usort($result, function ($a, $b) {
            if ($a['semester_info']['year_level'] !== $b['semester_info']['year_level']) {
                return $a['semester_info']['year_level'] <=> $b['semester_info']['year_level'];
            }

            return $a['semester_info']['semester_number'] <=> $b['semester_info']['semester_number'];
        });

        return [
            'semesters' => $result,
            'overall_summary' => [
                'total_subjects' => array_sum(array_column(array_column($result, 'summary'), 'total_subjects')),
                'completed_subjects' => array_sum(array_column(array_column($result, 'summary'), 'completed_subjects')),
                'current_subjects' => array_sum(array_column(array_column($result, 'summary'), 'current_subjects')),
                'failed_subjects' => array_sum(array_column(array_column($result, 'summary'), 'failed_subjects')),
                'retaking_subjects' => array_sum(array_column(array_column($result, 'summary'), 'retaking_subjects')),
                'withdrawn_subjects' => array_sum(array_column(array_column($result, 'summary'), 'withdrawn_subjects')),
                'not_started_subjects' => array_sum(array_column(array_column($result, 'summary'), 'not_started_subjects')),
                'registered_subjects' => array_sum(array_column(array_column($result, 'summary'), 'registered_subjects')),
            ],
        ];
    }

    /**
     * Determine study status for a unit
     */
    protected function determineStudyStatus($academicRecord, Collection $registrations): array
    {
        // If there's an academic record, check completion status. completion_status
        // means "finished", not "passed" — pass/fail is read from is_passed, so a
        // finished-but-failed unit takes the retake path, not "Completed".
        if ($academicRecord) {
            if ($academicRecord->completion_status === 'completed') {
                if ($academicRecord->isPassed()) {
                    return [
                        'status' => 'completed',
                        'label' => 'Completed',
                        'description' => 'Subject has been completed',
                    ];
                }

                // Finished but not passed: offer retake status.
                $hasRetakeRegistration = $registrations->contains(function ($registration) {
                    return in_array($registration->registration_status, ['pending', 'registered', 'confirmed']);
                });

                return $hasRetakeRegistration
                    ? [
                        'status' => 'retaking',
                        'label' => 'Retaking',
                        'description' => 'Retaking failed subject',
                    ]
                    : [
                        'status' => 'failed',
                        'label' => 'Failed',
                        'description' => 'Subject failed - needs retake',
                    ];
            } elseif ($academicRecord->completion_status === 'in_progress') {
                return [
                    'status' => 'in_progress',
                    'label' => 'In Progress',
                    'description' => 'Subject is currently being studied',
                ];
            } elseif ($academicRecord->completion_status === 'withdrawn') {
                return [
                    'status' => 'withdrawn',
                    'label' => 'Withdrawn',
                    'description' => 'Subject was withdrawn',
                ];
            }
        }

        // Check current registrations
        $activeRegistration = $registrations->first(function ($registration) {
            return in_array($registration->registration_status, ['pending', 'registered', 'confirmed']);
        });

        if ($activeRegistration) {
            return [
                'status' => 'registered',
                'label' => 'Registered',
                'description' => 'Registered for this semester',
            ];
        }

        // If no academic record and no active registration
        return [
            'status' => 'not_started',
            'label' => 'Not Started',
            'description' => 'Subject has not been started yet',
        ];
    }

    /**
     * Get grade information for a unit
     */
    protected function getGradeInfo($academicRecord): ?array
    {
        if (! $academicRecord) {
            return null;
        }

        return [
            'final_percentage' => $academicRecord->final_percentage,
            'final_letter_grade' => $academicRecord->final_letter_grade,
            'grade_points' => $academicRecord->grade_points,
            'completion_date' => $academicRecord->completion_date?->toDateString(),
            'grade_status' => $academicRecord->grade_status,
            'completion_status' => $academicRecord->completion_status,
            'is_passing' => $academicRecord->grade_points > 0,
        ];
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
            'total_credit_hours' => $curriculumUnits->sum(fn ($cu) => $cu->unit->credit_points ?? 0),
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

        $totalCredits = $curriculumUnits->sum(fn ($cu) => $cu->unit->credit_points ?? 0);
        $completedCredits = $curriculumUnits->whereIn('unit_id', $completedUnits->pluck('id'))->sum(fn ($cu) => $cu->unit->credit_points ?? 0);
        $currentCredits = $curriculumUnits->whereIn('unit_id', $currentEnrollments->pluck('id'))->sum(fn ($cu) => $cu->unit->credit_points ?? 0);

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

        return $curriculumUnits->groupBy('unit_scope')->map(function ($categoryUnits, $category) use ($completedUnitIds) {
            $totalUnits = $categoryUnits->count();
            $completedCount = $categoryUnits->whereIn('unit_id', $completedUnitIds)->count();

            return [
                'category' => $category ?? 'unknown',
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
            $prerequisiteGroups = $unit->prerequisiteGroups ?? collect();

            // Flatten all prerequisites from all groups
            $allPrerequisites = $prerequisiteGroups->flatMap(function ($group) {
                return $group->conditions->map(function ($condition) use ($group) {
                    return [
                        'unit' => $condition->requiredUnit,
                        'type' => $condition->type,
                        'group_logic' => $group->logic_operator,
                        'required_unit_id' => $condition->required_unit_id,
                    ];
                });
            })->filter(fn ($item) => $item['unit'] !== null);

            $tree[] = [
                'unit' => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_hours' => $unit->credit_points ?? 0,
                    'category' => $curriculumUnit->unit_scope ?? 'unknown',
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester_number,
                ],
                'status' => [
                    'is_completed' => $completedUnitIds->contains($unit->id),
                    'is_available' => $this->isUnitAvailableWithGroups($unit, $completedUnitIds),
                    'prerequisites_met' => $this->arePrerequisitesMetWithGroups($prerequisiteGroups, $completedUnitIds),
                ],
                'prerequisites' => $allPrerequisites->map(function ($prerequisite) use ($completedUnitIds) {
                    return [
                        'unit' => [
                            'id' => $prerequisite['unit']->id,
                            'code' => $prerequisite['unit']->code,
                            'name' => $prerequisite['unit']->name,
                        ],
                        'type' => $prerequisite['type'],
                        'group_logic' => $prerequisite['group_logic'],
                        'is_completed' => $completedUnitIds->contains($prerequisite['required_unit_id']),
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
            $unit = $curriculumUnit->unit;
            if ($unit->prerequisiteGroups && $unit->prerequisiteGroups->isNotEmpty()) {
                $chain = $this->buildPrerequisiteChainWithGroups($curriculumUnit, $curriculumUnits);
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
            return ! $completedUnitIds->contains($curriculumUnit->unit_id) &&
                ! $enrolledUnitIds->contains($curriculumUnit->unit_id) &&
                $this->isUnitAvailableWithGroups($curriculumUnit->unit, $completedUnitIds);
        })->map(function ($curriculumUnit) {
            return [
                'unit' => [
                    'id' => $curriculumUnit->unit->id,
                    'code' => $curriculumUnit->unit->code,
                    'name' => $curriculumUnit->unit->name,
                    'credit_hours' => $curriculumUnit->unit->credit_points ?? 0,
                    'category' => $curriculumUnit->unit_scope ?? 'unknown',
                ],
                'recommended_semester' => $curriculumUnit->semester_number,
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
            return ! $completedUnitIds->contains($curriculumUnit->unit_id) &&
                ! $enrolledUnitIds->contains($curriculumUnit->unit_id) &&
                ! $this->isUnitAvailableWithGroups($curriculumUnit->unit, $completedUnitIds);
        })->map(function ($curriculumUnit) use ($completedUnitIds) {
            $unit = $curriculumUnit->unit;
            $prerequisiteGroups = $unit->prerequisiteGroups ?? collect();

            // Get all missing prerequisites from all groups
            $missingPrerequisites = $prerequisiteGroups->flatMap(function ($group) use ($completedUnitIds) {
                return $group->conditions->filter(function ($condition) use ($completedUnitIds) {
                    return $condition->required_unit_id && ! $completedUnitIds->contains($condition->required_unit_id);
                })->map(function ($condition) use ($group) {
                    return [
                        'unit' => [
                            'id' => $condition->requiredUnit->id,
                            'code' => $condition->requiredUnit->code,
                            'name' => $condition->requiredUnit->name,
                        ],
                        'type' => $condition->type,
                        'group_logic' => $group->logic_operator,
                    ];
                });
            });

            return [
                'unit' => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_hours' => $unit->credit_points ?? 0,
                ],
                'missing_prerequisites' => $missingPrerequisites->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Get core requirements
     */
    protected function getCoreRequirements(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $coreUnits = $curriculumUnits->where('unit_scope', 'common'); // Core units are typically common
        $completedUnitIds = $completedUnits->pluck('id');

        return [
            'total_units' => $coreUnits->count(),
            'completed_units' => $coreUnits->whereIn('unit_id', $completedUnitIds)->count(),
            'total_credits' => $coreUnits->sum(fn ($cu) => $cu->unit->credit_points ?? 0),
            'completed_credits' => $coreUnits->whereIn('unit_id', $completedUnitIds)->sum(fn ($cu) => $cu->unit->credit_points ?? 0),
            'units' => $coreUnits->map(function ($curriculumUnit) use ($completedUnitIds) {
                return [
                    'unit' => [
                        'id' => $curriculumUnit->unit->id,
                        'code' => $curriculumUnit->unit->code,
                        'name' => $curriculumUnit->unit->name,
                        'credit_hours' => $curriculumUnit->unit->credit_points ?? 0,
                    ],
                    'is_completed' => $completedUnitIds->contains($curriculumUnit->unit_id),
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester_number,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get elective requirements
     */
    protected function getElectiveRequirements(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $electiveUnits = $curriculumUnits->where('unit_scope', 'cross_program'); // Cross-program units are typically electives
        $completedUnitIds = $completedUnits->pluck('id');

        return [
            'total_units' => $electiveUnits->count(),
            'completed_units' => $electiveUnits->whereIn('unit_id', $completedUnitIds)->count(),
            'total_credits' => $electiveUnits->sum(fn ($cu) => $cu->unit->credit_points ?? 0),
            'completed_credits' => $electiveUnits->whereIn('unit_id', $completedUnitIds)->sum(fn ($cu) => $cu->unit->credit_points ?? 0),
            'units' => $electiveUnits->map(function ($curriculumUnit) use ($completedUnitIds) {
                return [
                    'unit' => [
                        'id' => $curriculumUnit->unit->id,
                        'code' => $curriculumUnit->unit->code,
                        'name' => $curriculumUnit->unit->name,
                        'credit_hours' => $curriculumUnit->unit->credit_points ?? 0,
                    ],
                    'is_completed' => $completedUnitIds->contains($curriculumUnit->unit_id),
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester_number,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get specialization requirements
     */
    protected function getSpecializationRequirements(Collection $curriculumUnits, Collection $completedUnits): array
    {
        $specializationUnits = $curriculumUnits->where('unit_scope', 'specialization_specific');
        $completedUnitIds = $completedUnits->pluck('id');

        return [
            'total_units' => $specializationUnits->count(),
            'completed_units' => $specializationUnits->whereIn('unit_id', $completedUnitIds)->count(),
            'total_credits' => $specializationUnits->sum(fn ($cu) => $cu->unit->credit_points ?? 0),
            'completed_credits' => $specializationUnits->whereIn('unit_id', $completedUnitIds)->sum(fn ($cu) => $cu->unit->credit_points ?? 0),
            'units' => $specializationUnits->map(function ($curriculumUnit) use ($completedUnitIds) {
                return [
                    'unit' => [
                        'id' => $curriculumUnit->unit->id,
                        'code' => $curriculumUnit->unit->code,
                        'name' => $curriculumUnit->unit->name,
                        'credit_hours' => $curriculumUnit->unit->credit_points ?? 0,
                    ],
                    'is_completed' => $completedUnitIds->contains($curriculumUnit->unit_id),
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester_number,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get general education requirements
     */
    protected function getGeneralEducationRequirements(Collection $curriculumUnits, Collection $completedUnits): array
    {
        // Filter for general education units - these might be common units or have specific characteristics
        $genEdUnits = $curriculumUnits->filter(function ($curriculumUnit) {
            return $curriculumUnit->unit_scope === 'common' &&
                ($curriculumUnit->year_level <= 2); // Typically first/second year units
        });
        $completedUnitIds = $completedUnits->pluck('id');

        return [
            'total_units' => $genEdUnits->count(),
            'completed_units' => $genEdUnits->whereIn('unit_id', $completedUnitIds)->count(),
            'total_credits' => $genEdUnits->sum(fn ($cu) => $cu->unit->credit_points ?? 0),
            'completed_credits' => $genEdUnits->whereIn('unit_id', $completedUnitIds)->sum(fn ($cu) => $cu->unit->credit_points ?? 0),
            'units' => $genEdUnits->map(function ($curriculumUnit) use ($completedUnitIds) {
                return [
                    'unit' => [
                        'id' => $curriculumUnit->unit->id,
                        'code' => $curriculumUnit->unit->code,
                        'name' => $curriculumUnit->unit->name,
                        'credit_hours' => $curriculumUnit->unit->credit_points ?? 0,
                    ],
                    'is_completed' => $completedUnitIds->contains($curriculumUnit->unit_id),
                    'year_level' => $curriculumUnit->year_level,
                    'semester' => $curriculumUnit->semester_number,
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

        return $curriculumUnits->groupBy('unit_scope')->map(function ($categoryUnits, $category) use ($completedUnitIds) {
            $totalCredits = $categoryUnits->sum(fn ($cu) => $cu->unit->credit_points ?? 0);
            $completedCredits = $categoryUnits->whereIn('unit_id', $completedUnitIds)->sum(fn ($cu) => $cu->unit->credit_points ?? 0);

            return [
                'category' => $category ?? 'unknown',
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
        $completedCredits = TranscriptEntry::query()
            ->where('student_id', $student->id)
            ->where('is_passed', true)
            ->sum('credit_points_earned');

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
        return $curriculumUnits->groupBy('unit_scope')->map(function ($items) {
            return $items->count();
        })->toArray();
    }

    protected function groupByYearLevel(Collection $curriculumUnits): array
    {
        return $curriculumUnits->groupBy('year_level')->map(function ($items) {
            return $items->count();
        })->toArray();
    }

    protected function groupBySemester(Collection $curriculumUnits): array
    {
        return $curriculumUnits->groupBy('semester_number')->map(function ($items) {
            return $items->count();
        })->toArray();
    }

    protected function isUnitAvailableWithGroups(Unit $unit, Collection $completedUnitIds): bool
    {
        $prerequisiteGroups = $unit->prerequisiteGroups ?? collect();

        return $this->arePrerequisitesMetWithGroups($prerequisiteGroups, $completedUnitIds);
    }

    protected function arePrerequisitesMetWithGroups(Collection $prerequisiteGroups, Collection $completedUnitIds): bool
    {
        if ($prerequisiteGroups->isEmpty()) {
            return true;
        }

        foreach ($prerequisiteGroups as $group) {
            $groupMet = $this->isPrerequisiteGroupMet($group, $completedUnitIds);

            // If any group is met (OR logic between groups), prerequisites are satisfied
            if ($groupMet) {
                return true;
            }
        }

        return false;
    }

    protected function isPrerequisiteGroupMet(UnitPrerequisiteGroup $group, Collection $completedUnitIds): bool
    {
        $conditions = $group->conditions ?? collect();

        if ($conditions->isEmpty()) {
            return true;
        }

        $operator = $group->logic_operator ?? 'AND';

        foreach ($conditions as $condition) {
            $conditionMet = $this->isPrerequisiteConditionMet($condition, $completedUnitIds);

            if ($operator === 'AND' && ! $conditionMet) {
                return false;
            }
            if ($operator === 'OR' && $conditionMet) {
                return true;
            }
        }

        return $operator === 'AND';
    }

    protected function isPrerequisiteConditionMet(UnitPrerequisiteCondition $condition, Collection $completedUnitIds): bool
    {
        if ($condition->required_unit_id) {
            return $completedUnitIds->contains($condition->required_unit_id);
        }

        // For other condition types (credits, free text), assume met for now
        return true;
    }

    protected function buildPrerequisiteChainWithGroups(CurriculumUnit $curriculumUnit, Collection $allUnits): array
    {
        $chain = [$curriculumUnit->unit->code];
        $unit = $curriculumUnit->unit;
        $prerequisiteGroups = $unit->prerequisiteGroups ?? collect();

        foreach ($prerequisiteGroups as $group) {
            foreach ($group->conditions as $condition) {
                if ($condition->required_unit_id) {
                    $prereqUnit = $allUnits->where('unit_id', $condition->required_unit_id)->first();
                    if ($prereqUnit instanceof CurriculumUnit) {
                        $subChain = $this->buildPrerequisiteChainWithGroups($prereqUnit, $allUnits);
                        $chain = array_merge($subChain, $chain);
                    }
                }
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
        if (! $student->enrollment_date) {
            return 1;
        }

        $yearsEnrolled = $student->enrollment_date->diffInYears(now()) + 1;

        return min($yearsEnrolled, $student->program->duration_years);
    }

    protected function calculateSemestersCompleted(Student $student): int
    {
        return TranscriptEntry::query()
            ->where('student_id', $student->id)
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
        if (! $student->enrollment_date) {
            return 0;
        }

        $monthsEnrolled = $student->enrollment_date->diffInMonths(now());

        return (int) floor($monthsEnrolled / 6); // Assuming 6 months per semester
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
        if (! $student->expected_graduation_date) {
            return 0;
        }

        $monthsRemaining = now()->diffInMonths($student->expected_graduation_date);

        return (int) ceil($monthsRemaining / 6);
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

    /**
     * Check if a unit is in the student's curriculum
     *
     * @return array{is_in_curriculum: bool, curriculum_unit: ?CurriculumUnit, reason: string}
     */
    public function isUnitInStudentCurriculum(Student $student, int $unitId): array
    {
        // Check if student has curriculum version assigned
        if (! $student->curriculum_version_id) {
            return [
                'is_in_curriculum' => false,
                'curriculum_unit' => null,
                'reason' => 'Student is not assigned to any curriculum version',
            ];
        }

        // Get curriculum unit for this student and unit
        $curriculumUnit = CurriculumUnit::where('curriculum_version_id', $student->curriculum_version_id)
            ->where('unit_id', $unitId)
            ->with(['unit', 'curriculumVersion'])
            ->first();

        if (! $curriculumUnit) {
            return [
                'is_in_curriculum' => false,
                'curriculum_unit' => null,
                'reason' => 'Unit is not in the student\'s curriculum',
            ];
        }

        return [
            'is_in_curriculum' => true,
            'curriculum_unit' => $curriculumUnit,
            'reason' => 'Unit is found in student\'s curriculum',
        ];
    }

    /**
     * Check if a unit is in the student's curriculum (simple boolean version)
     */
    public function isUnitInCurriculum(Student $student, int $unitId): bool
    {
        return $this->isUnitInStudentCurriculum($student, $unitId)['is_in_curriculum'];
    }
}
