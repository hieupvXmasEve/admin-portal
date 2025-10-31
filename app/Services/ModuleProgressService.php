<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Module;
use App\Models\Student;
use Illuminate\Support\Collection;

class ModuleProgressService
{
    public function getStudentModuleProgress(Student $student): Collection
    {
        $curriculumVersion = $student->curriculumVersion;

        if (! $curriculumVersion || ! $curriculumVersion->hasModularStructure()) {
            return collect([]);
        }

        $modules = $curriculumVersion->modules()
            ->with(['units', 'prerequisiteModule'])
            ->get();

        return $modules->map(function (Module $module) use ($student) {
            return $this->calculateModuleProgress($module, $student);
        });
    }

    public function calculateModuleProgress(Module $module, Student $student): array
    {
        $subUnits = $module->units;
        $unitIds = $subUnits->pluck('id');

        $academicRecords = $student->academicRecords()
            ->whereIn('unit_id', $unitIds)
            ->with(['unit', 'courseOffering'])
            ->get();

        // Use grading_type from module_units pivot, not from course_offering
        [$gradedRecords, $passFailRecords] = $academicRecords->partition(function ($record) use ($subUnits) {
            $unit = $subUnits->firstWhere('id', $record->unit_id);
            return $unit && $unit->pivot->grading_type === 'grade';
        });

        $moduleGrade = $this->calculateModuleGrade($gradedRecords, $module);
        $moduleStatus = $this->calculateModuleStatus($academicRecords, $subUnits);

        $completedCount = $academicRecords->whereIn('completion_status', ['completed', 'failed'])->count();
        $totalCount = $subUnits->count();

        return [
            'module' => $module,
            'grade' => $moduleGrade,
            'status' => $moduleStatus,
            'completion' => "{$completedCount}/{$totalCount}",
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'sub_units' => $this->mapSubUnitsProgress($subUnits, $academicRecords),
        ];
    }

    private function calculateModuleGrade(Collection $gradedRecords, Module $module): ?float
    {
        if ($gradedRecords->isEmpty()) {
            return null;
        }

        $subUnits = $module->units()->get();
        $hasWeights = $subUnits->whereNotNull('pivot.weight')->isNotEmpty();

        if ($hasWeights) {
            return $this->calculateWeightedAverage($gradedRecords, $subUnits);
        }

        return round($gradedRecords->avg('final_percentage'), 2);
    }

    private function calculateWeightedAverage(Collection $gradedRecords, Collection $subUnits): float
    {
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($gradedRecords as $record) {
            $unit = $subUnits->firstWhere('id', $record->unit_id);
            $weight = $unit?->pivot->weight ?? 1;

            $weightedSum += $record->final_percentage * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight == 0) {
            return 0;
        }

        return round($weightedSum / $totalWeight, 2);
    }

    private function calculateModuleStatus(Collection $academicRecords, Collection $subUnits): string
    {
        if ($academicRecords->isEmpty()) {
            return 'not_started';
        }

        $completedCount = $academicRecords->whereIn('completion_status', ['completed', 'failed'])->count();

        if ($completedCount < $subUnits->count()) {
            return 'in_progress';
        }

        $allPassed = $academicRecords->every(fn ($record) => $record->isPassed());

        return $allPassed ? 'passed' : 'failed';
    }

    private function mapSubUnitsProgress(Collection $subUnits, Collection $academicRecords): Collection
    {
        return $subUnits->map(function ($unit) use ($academicRecords) {
            $record = $academicRecords->firstWhere('unit_id', $unit->id);

            return [
                'unit' => $unit, // Unit with pivot (grading_type from module_units)
                'academic_record' => $record,
                'grade' => $record?->final_percentage,
                'letter_grade' => $record?->final_letter_grade,
                'status' => $record?->completion_status ?? 'not_enrolled',
                'grading_type' => $unit->pivot->grading_type ?? 'grade', // From module_units pivot
            ];
        });
    }
}
