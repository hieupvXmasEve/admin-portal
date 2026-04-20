<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Egc;

use App\Models\EgcBlock;
use App\Models\FinanceCharge;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class PreviewEgcChargeGenerationQuery
{
    public function handle(int $semesterId): array
    {
        $campusId = app()->bound('campus') ? app('campus')?->id : null;

        $query = Student::where('status', 'intake_pre_uni_gc')
            ->with(['egcProgress' => function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            }]);

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        $students = $query->get();

        $eligible = [];
        $ineligible = [];
        $warnings = [];

        foreach ($students as $student) {
            $row = $this->classify($student, $semesterId);

            match ($row['eligibility_status']) {
                'eligible' => $eligible[] = $row,
                'ineligible' => $ineligible[] = $row,
                default => $warnings[] = $row,
            };
        }

        return [
            'eligible_students' => $eligible,
            'ineligible_students' => $ineligible,
            'warning_students' => $warnings,
            'summary' => [
                'eligible_count' => count($eligible),
                'ineligible_count' => count($ineligible),
                'warning_count' => count($warnings),
                'total_count' => $students->count(),
            ],
        ];
    }

    private function classify(Student $student, int $semesterId): array
    {
        $base = $this->baseRow($student);

        $currentLevel = (int) ($student->gc_current_level ?? 0);
        $totalLevels = (int) ($student->gc_total_levels ?? 0);

        if ($currentLevel === 0) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_current_level',
            ]);
        }

        if ($totalLevels === 0) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_total_levels',
            ]);
        }

        if ($currentLevel > $totalLevels) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'exceeded_max_level',
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
            ]);
        }

        // Skip if student is actively studying their current level this semester
        if ($this->isStudyingLevel($student->id, $semesterId, $currentLevel)) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'currently_studying',
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
            ]);
        }

        // Use FinanceCharge as source of truth for already-charged count
        $chargedCount = FinanceCharge::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->count();

        $deferredBlocks = EgcBlock::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->whereNull('finance_charge_id')
            ->get();

        $deferredCount = $deferredBlocks->count();

        $levelsRemaining = $totalLevels - $currentLevel + 1;
        $maxChargeableBlocks = min(2, max(0, $levelsRemaining - $chargedCount));

        if ($chargedCount >= 2 && $deferredCount === 0) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'already_fully_charged',
                'already_charged_blocks' => $chargedCount,
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
            ]);
        }

        if ($maxChargeableBlocks === 0 && $deferredCount === 0) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'no_chargeable_blocks_remaining',
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
            ]);
        }

        return array_merge($base, [
            'eligibility_status' => 'eligible',
            'eligibility_reason' => null,
            'current_level' => $currentLevel,
            'total_levels' => $totalLevels,
            'already_charged_blocks' => $chargedCount,
            'has_deferred_blocks' => $deferredCount > 0,
            'max_chargeable_blocks' => $maxChargeableBlocks,
            'deferred_blocks' => $deferredBlocks->values()->map(fn ($b) => [
                'block_number' => $b->block_number,
                'level_number' => $b->level_number,
            ])->all(),
            'chargeable_levels' => $this->resolveChargeableLevels($student->id, $currentLevel, $maxChargeableBlocks),
        ]);
    }

    private function isStudyingLevel(int $studentId, int $semesterId, int $levelNumber): bool
    {
        return DB::table('academic_records')
            ->join('units', 'academic_records.unit_id', '=', 'units.id')
            ->where('academic_records.student_id', $studentId)
            ->where('academic_records.semester_id', $semesterId)
            ->where('units.unit_type', 'egc')
            ->where('units.level', $levelNumber)
            ->where('academic_records.completion_status', 'in_progress')
            ->exists();
    }

    /**
     * Resolve which levels will be charged, annotated with retake eligibility.
     *
     * @return array<int, array{level_number: int, is_retake: bool, amount: int}>
     */
    private function resolveChargeableLevels(int $studentId, int $currentLevel, int $count): array
    {
        $levels = [];

        for ($i = 0; $i < $count; $i++) {
            $levelNumber = $currentLevel + $i;

            $levels[] = [
                'level_number' => $levelNumber,
                'is_retake' => $this->isRetakeEligible($studentId, $levelNumber),
                'amount' => 15_000_000,
            ];
        }

        return $levels;
    }

    private function isRetakeEligible(int $studentId, int $levelNumber): bool
    {
        return EgcBlock::where('student_id', $studentId)
            ->where('level_number', $levelNumber)
            ->where('result', EgcBlock::RESULT_FAIL)
            ->where('attendance_rate', '>=', 80)
            ->whereNull('retake_discount_id')
            ->exists();
    }

    private function baseRow(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'student_code' => $student->student_id,
            'eligibility_status' => 'eligible',
            'eligibility_reason' => null,
            'current_level' => null,
            'total_levels' => null,
            'already_charged_blocks' => 0,
            'has_deferred_blocks' => false,
            'max_chargeable_blocks' => 0,
            'deferred_blocks' => [],
            'chargeable_levels' => [],
        ];
    }
}
