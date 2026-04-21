<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Egc;

use App\Models\EgcBlock;
use App\Models\FinanceCharge;
use App\Models\Student;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PreviewEgcChargeGenerationQuery
{
    public function handle(int $semesterId, array $filters = [], ?int $campusId = null): array
    {
        $rows = $this->collectRows($semesterId, $filters, $campusId);
        $eligible = $rows->where('eligibility_status', 'eligible')->values();
        $ineligible = $rows->where('eligibility_status', 'ineligible')->values();
        $warnings = $rows->where('eligibility_status', 'warning')->values();
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        return [
            'eligible_students' => $this->paginateCollection($eligible, $perPage, $page),
            'ineligible_students' => $ineligible->all(),
            'warning_students' => $warnings->all(),
            'summary' => [
                'eligible_count' => $eligible->count(),
                'ineligible_count' => $ineligible->count(),
                'warning_count' => $warnings->count(),
                'total_count' => $rows->count(),
            ],
        ];
    }

    public function resolveEligibleStudents(int $semesterId, array $filters = [], ?int $campusId = null): Collection
    {
        return $this->collectRows($semesterId, $filters, $campusId)
            ->where('eligibility_status', 'eligible')
            ->values();
    }

    private function collectRows(int $semesterId, array $filters = [], ?int $campusId = null): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $ignoredStudentIds = collect($filters['ignore_student_ids'] ?? [])
            ->filter(fn (mixed $studentId): bool => is_string($studentId) && trim($studentId) !== '')
            ->map(fn (string $studentId): string => trim($studentId))
            ->values()
            ->all();

        $students = Student::query()
            ->where('status', 'intake_pre_uni_gc')
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->when($ignoredStudentIds !== [], fn ($query) => $query->whereNotIn('student_id', $ignoredStudentIds))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($studentQuery) use ($search) {
                    $studentQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('student_id')
            ->get();

        return $students->map(fn (Student $student) => $this->classify($student, $semesterId));
    }

    private function classify(Student $student, int $semesterId): array
    {
        $base = $this->baseRow($student);

        if ($student->gc_current_level === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_current_level',
            ]);
        }

        if ($student->gc_total_levels === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_total_levels',
            ]);
        }

        $currentLevel = (int) $student->gc_current_level;
        $totalLevels = (int) $student->gc_total_levels;

        if ($totalLevels <= 0) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_total_levels',
            ]);
        }

        if ($currentLevel >= $totalLevels) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => 'exceeded_max_level',
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
            ]);
        }

        // Student đang học current level → phát sinh phí dự kiến cho level tiếp theo
        // Student đã học xong → phát sinh phí cho current level
        $isStudying = $this->isStudyingLevel($student->id, $currentLevel);
        $effectiveStartLevel = $isStudying ? $currentLevel + 1 : $currentLevel;

        if ($effectiveStartLevel >= $totalLevels) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => $isStudying ? 'studying_last_level' : 'exceeded_max_level',
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
            ]);
        }

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
        $levelsRemaining = max(0, $totalLevels - $effectiveStartLevel);
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
            'is_studying' => $isStudying,
            'already_charged_blocks' => $chargedCount,
            'student_email' => $student->email,
            'has_deferred_blocks' => $deferredCount > 0,
            'max_chargeable_blocks' => $maxChargeableBlocks,
            'deferred_blocks' => $deferredBlocks->values()->map(fn ($b) => [
                'block_number' => $b->block_number,
                'level_number' => $b->level_number,
            ])->all(),
            'chargeable_levels' => $this->resolveChargeableLevels($student->id, $effectiveStartLevel, $maxChargeableBlocks),
        ]);
    }

    private function isStudyingLevel(int $studentId, int $levelNumber): bool
    {
        // Kiểm tra semester gần nhất có EGC record của student
        // Không dùng target semester vì semester tương lai chưa có academic records
        return DB::table('academic_records as ar')
            ->join('units', 'ar.unit_id', '=', 'units.id')
            ->where('ar.student_id', $studentId)
            ->where('units.unit_type', 'egc')
            ->where('units.level', $levelNumber)
            ->where('ar.completion_status', 'in_progress')
            ->where('ar.semester_id', function ($query) use ($studentId): void {
                $query->selectRaw('MAX(ar2.semester_id)')
                    ->from('academic_records as ar2')
                    ->join('units as u2', 'ar2.unit_id', '=', 'u2.id')
                    ->where('ar2.student_id', $studentId)
                    ->where('u2.unit_type', 'egc');
            })
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
            'student_email' => $student->email,
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

    private function paginateCollection(Collection $items, int $perPage, int $page): LengthAwarePaginator
    {
        $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;
        $page = max(1, $page);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
