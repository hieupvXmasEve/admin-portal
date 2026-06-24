<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Module;
use App\Models\Student;
use Illuminate\Support\Collection;

class ModuleProgressService
{
    public function __construct(
        private readonly ModuleGradeCalculator $moduleGradeCalculator = new ModuleGradeCalculator,
    ) {}

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

        // Graded vs pass/fail is decided by the module_units pivot inside the calculator.
        $moduleGrade = $this->moduleGradeCalculator->calculate($academicRecords, $module);
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
