<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Egc;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use App\Modules\Finance\Support\EgcBlockGenerationClassifier;
use App\Modules\Finance\Support\EgcLevelFeeResolver;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\AcademicEgcBlockData;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentCollectionEligibilityReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PreviewEgcChargeGenerationQuery
{
    private ?EgcLevelFeeResolver $egcFeeResolver = null;

    private ?EgcBlockGenerationClassifier $blockClassifier = null;

    private ?AcademicFinanceChargeSourceGateway $academicSources = null;

    public function handle(int $semesterId, array $filters = [], ?int $campusId = null): array
    {
        $rows = $this->collectRows($semesterId, $filters, $campusId);
        $eligible = $rows->where('eligibility_status', 'eligible')->values();
        $ineligible = $rows->where('eligibility_status', 'ineligible')->values();
        $warnings = $rows->where('eligibility_status', 'warning')->values();
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        // UI-SAFE-3: execute generates for ALL eligible students (the server
        // recomputes the full filter), not just the current page. Surface the
        // full-scope projection so the confirm total matches what executes, even
        // though block_count overrides are entered page by page. Students not
        // adjusted on a page default to their max_chargeable_blocks.
        $projectedBlockCount = (int) $eligible->sum('max_chargeable_blocks');
        $projectedTotalAmount = (float) $eligible->sum(
            fn (array $student) => collect($student['chargeable_levels'] ?? [])->sum('amount')
        );

        return [
            'eligible_students' => $this->paginateCollection($eligible, $perPage, $page),
            'ineligible_students' => $ineligible->all(),
            'warning_students' => $warnings->all(),
            'summary' => [
                'eligible_count' => $eligible->count(),
                'ineligible_count' => $ineligible->count(),
                'warning_count' => $warnings->count(),
                'total_count' => $rows->count(),
                'projected_block_count' => $projectedBlockCount,
                'projected_total_amount' => $projectedTotalAmount,
            ],
        ];
    }

    public function resolveEligibleStudents(int $semesterId, array $filters = [], ?int $campusId = null): Collection
    {
        return $this->collectRows($semesterId, $filters, $campusId)
            ->where('eligibility_status', 'eligible')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function collectClassificationRows(int $semesterId, array $filters = [], ?int $campusId = null): Collection
    {
        return $this->collectRows($semesterId, $filters, $campusId);
    }

    private function collectRows(int $semesterId, array $filters = [], ?int $campusId = null): Collection
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $ignoredStudentIds = collect($filters['ignore_student_ids'] ?? [])
            ->filter(fn (mixed $studentId): bool => is_string($studentId) && trim($studentId) !== '')
            ->map(fn (string $studentId): string => trim($studentId))
            ->values()
            ->all();

        $studentIds = collect($filters['student_ids'] ?? [])
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $eligibleStudentIds = app(StudentCollectionEligibilityReader::class)->eligibleStudentIds(['egc'], $campusId);
        if ($studentIds !== []) {
            $eligibleStudentIds = array_values(array_intersect($eligibleStudentIds, $studentIds));
        }
        if ($search !== '') {
            $eligibleStudentIds = array_values(array_intersect(
                $eligibleStudentIds,
                app(StudentReferenceReader::class)->idsMatchingSearch($search, $campusId),
            ));
        }

        $references = app(StudentReferenceReader::class)->findMany($eligibleStudentIds);
        $enrollments = app(ProgramEnrollmentReader::class)->forStudentIds(array_keys($references));

        return collect($references)
            ->filter(fn (StudentReference $reference): bool => ! in_array($reference->studentCode, $ignoredStudentIds, true))
            ->sortBy(fn (StudentReference $reference): string => $reference->studentCode)
            ->map(fn (StudentReference $reference): array => $this->classify(
                $reference,
                $enrollments[$reference->id] ?? app(ProgramEnrollmentReader::class)->forStudentId($reference->id),
                $semesterId,
            ));
    }

    private function classify(StudentReference $student, ProgramEnrollmentSummary $enrollment, int $semesterId): array
    {
        $base = $this->baseRow($student);

        if ($enrollment->egcCurrentLevel === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_current_level',
            ]);
        }

        if ($enrollment->egcTotalLevels === null) {
            return array_merge($base, [
                'eligibility_status' => 'warning',
                'eligibility_reason' => 'missing_total_levels',
            ]);
        }

        $currentLevel = $enrollment->egcCurrentLevel;
        $totalLevels = $enrollment->egcTotalLevels;

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
        $isStudying = $this->academicSources()->isStudentStudyingEgcLevel($student->id, $currentLevel);
        $effectiveStartLevel = $isStudying ? $currentLevel + 1 : $currentLevel;

        if ($effectiveStartLevel >= $totalLevels) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => $isStudying ? 'studying_last_level' : 'exceeded_max_level',
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
            ]);
        }

        $blockState = $this->blockClassifier()->classify($student->id, $semesterId);

        if ($blockState->isBlocked()) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => $blockState->reason,
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
                'already_charged_blocks' => $blockState->collectibleBlockCount,
                'existing_egc_blocks' => $this->egcBlockRows($blockState->blocks),
            ]);
        }

        if ($blockState->isAlreadyGenerated()) {
            return array_merge($base, [
                'eligibility_status' => 'ineligible',
                'eligibility_reason' => $blockState->reason ?? 'already_fully_charged',
                'already_charged_blocks' => $blockState->collectibleBlockCount,
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
                'existing_egc_blocks' => $this->egcBlockRows($blockState->blocks),
            ]);
        }

        if ($blockState->shouldReissue()) {
            $maxChargeableBlocks = min(2, $blockState->reissueBlocks->count());
            $chargeableLevels = $this->resolveChargeableLevelsForBlocks(
                $student->id,
                $blockState->reissueBlocks,
                $maxChargeableBlocks,
                $totalLevels,
            );

            if ($chargeableLevels === []) {
                return array_merge($base, [
                    'eligibility_status' => 'ineligible',
                    'eligibility_reason' => $blockState->reason,
                    'current_level' => $currentLevel,
                    'total_levels' => $totalLevels,
                    'already_charged_blocks' => $blockState->collectibleBlockCount,
                    'existing_egc_blocks' => $this->egcBlockRows($blockState->blocks),
                ]);
            }

            return array_merge($base, [
                'eligibility_status' => 'eligible',
                'eligibility_reason' => $blockState->reason,
                'generation_mode' => 'reissue',
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
                'is_studying' => $isStudying,
                'already_charged_blocks' => $blockState->collectibleBlockCount,
                'student_email' => $student->email,
                'has_deferred_blocks' => false,
                'max_chargeable_blocks' => count($chargeableLevels),
                'existing_egc_blocks' => $this->egcBlockRows($blockState->blocks),
                'chargeable_levels' => $chargeableLevels,
            ]);
        }

        $chargedCount = FinanceCharge::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->count();

        $semesterBlocks = collect($this->academicSources()->egcBlocksForStudentSemester($student->id, $semesterId));
        $chargesByBlock = app(EgcBlockFinanceResolver::class)->chargesFor($semesterBlocks);
        $deferredBlocks = $semesterBlocks
            ->filter(fn (AcademicEgcBlockData $block): bool => $chargesByBlock->get($block->id) === null);

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
                // FIN-06: same canonical fee resolver as execute so the preview
                // amount equals the charge that will be created.
                'amount' => (int) $this->egcFeeResolver()->resolve($levelNumber),
            ];
        }

        return $levels;
    }

    /**
     * Resolve chargeable levels from existing EGC block slots for a safe reissue.
     *
     * @param  Collection<int, AcademicEgcBlockData>  $blocks
     * @return array<int, array{level_number: int, is_retake: bool, amount: int}>
     */
    private function resolveChargeableLevelsForBlocks(int $studentId, Collection $blocks, int $count, int $totalLevels): array
    {
        return $blocks
            ->take($count)
            ->filter(fn (AcademicEgcBlockData $block): bool => (int) $block->level_number < $totalLevels)
            ->values()
            ->map(fn (AcademicEgcBlockData $block): array => [
                'level_number' => (int) $block->level_number,
                'is_retake' => $this->isRetakeEligible($studentId, (int) $block->level_number),
                'amount' => (int) $this->egcFeeResolver()->resolve((int) $block->level_number),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, AcademicEgcBlockData>  $blocks
     * @return array<int, array{block_number: int, level_number: int, finance_source_ref: string}>
     */
    private function egcBlockRows(Collection $blocks): array
    {
        return $blocks
            ->values()
            ->map(fn (AcademicEgcBlockData $block): array => [
                'block_number' => (int) $block->block_number,
                'level_number' => (int) $block->level_number,
                'finance_source_ref' => app(EgcBlockFinanceResolver::class)->sourceRef($block),
            ])
            ->all();
    }

    private function egcFeeResolver(): EgcLevelFeeResolver
    {
        return $this->egcFeeResolver ??= new EgcLevelFeeResolver;
    }

    private function blockClassifier(): EgcBlockGenerationClassifier
    {
        return $this->blockClassifier ??= app(EgcBlockGenerationClassifier::class);
    }

    private function isRetakeEligible(int $studentId, int $levelNumber): bool
    {
        return $this->academicSources()->isEgcRetakeEligible($studentId, $levelNumber);
    }

    private function baseRow(StudentReference $student): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => $student->fullName,
            'student_code' => $student->studentCode,
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

    private function academicSources(): AcademicFinanceChargeSourceGateway
    {
        return $this->academicSources ??= app(AcademicFinanceChargeSourceGateway::class);
    }
}
