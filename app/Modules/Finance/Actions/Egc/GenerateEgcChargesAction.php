<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Models\EgcBlock;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use App\Modules\Finance\Support\EgcBlockGenerationClassifier;
use App\Modules\Finance\Support\EgcBlockGenerationState;
use App\Modules\Finance\Support\EgcLevelFeeResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateEgcChargesAction
{
    /**
     * Deprecated flat fee. Retained for backward compatibility; the canonical
     * source is now {@see EgcLevelFeeResolver} (FIN-06) via intake pricing.
     */
    public const CHARGE_AMOUNT = EgcLevelFeeResolver::FALLBACK_FEE;

    /**
     * Generate EGC charges for a list of students in a semester.
     *
     * @param  array{semester_id: int, due_date?: string, students: array<array{student_id: int, block_count: int}>}  $data
     */
    public static function run(array $data): array
    {
        $semesterId = (int) $data['semester_id'];
        $dueDate = (string) ($data['due_date'] ?? now()->addDays(30)->toDateString());
        $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($data['students'] as $studentData) {
            $studentId = (int) $studentData['student_id'];
            $blockCount = (int) ($studentData['block_count'] ?? 2);
            $student = Student::query()
                ->select(['id', 'gc_current_level', 'gc_total_levels'])
                ->find($studentId);
            $currentLevel = $student?->gc_current_level !== null
                ? (int) $student->gc_current_level
                : (int) ($studentData['current_level'] ?? 0);
            $totalLevels = $student?->gc_total_levels !== null
                ? (int) $student->gc_total_levels
                : 0;

            // Student đang học current level → phí dự kiến cho level tiếp theo
            // Student đã học xong → phí cho current level hiện tại
            $isStudying = self::isStudyingLevel($studentId, $currentLevel);
            $effectiveStartLevel = $isStudying ? $currentLevel + 1 : $currentLevel;

            if ($totalLevels <= 0 || $effectiveStartLevel >= $totalLevels) {
                $results['skipped'] += $blockCount;

                continue;
            }

            $blockState = app(EgcBlockGenerationClassifier::class)->classify($student, $semesterId);

            if ($blockState->isBlocked()) {
                $results['skipped'] += $blockCount;
                $results['errors'][] = ['student_id' => $studentId, 'error' => $blockState->reason];

                continue;
            }

            if ($blockState->isAlreadyGenerated()) {
                $results['skipped'] += $blockCount;

                continue;
            }

            if ($blockState->shouldReissue()) {
                try {
                    DB::transaction(function () use ($studentId, $semesterId, $dueDate, $blockCount, $totalLevels, $blockState, &$results) {
                        self::reissueExistingBlocks($studentId, $semesterId, $dueDate, $blockCount, $totalLevels, $blockState, $results);
                    });
                } catch (\Exception $e) {
                    Log::error('GenerateEgcChargesAction reissue failed', [
                        'student_id' => $studentId,
                        'semester_id' => $semesterId,
                        'error' => $e->getMessage(),
                    ]);
                    $results['errors'][] = ['student_id' => $studentId, 'error' => $e->getMessage()];
                }

                continue;
            }

            $existingChargeCount = FinanceCharge::where('student_id', $studentId)
                ->where('semester_id', $semesterId)
                ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->count();

            if ($existingChargeCount >= 2) {
                $results['skipped'] += $blockCount;

                continue;
            }

            try {
                DB::transaction(function () use ($studentId, $semesterId, $dueDate, $blockCount, $effectiveStartLevel, $totalLevels, $existingChargeCount, &$results) {
                    self::generateForStudent($studentId, $semesterId, $dueDate, $blockCount, $effectiveStartLevel, $totalLevels, $existingChargeCount, $results);
                });
            } catch (\Exception $e) {
                Log::error('GenerateEgcChargesAction failed', [
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'error' => $e->getMessage(),
                ]);
                $results['errors'][] = ['student_id' => $studentId, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    private static function reissueExistingBlocks(
        int $studentId,
        int $semesterId,
        string $dueDate,
        int $blockCount,
        int $totalLevels,
        EgcBlockGenerationState $blockState,
        array &$results,
    ): void {
        $created = 0;

        /** @var Collection<int, EgcBlock> $blocks */
        $blocks = $blockState->reissueBlocks->take($blockCount);

        foreach ($blocks as $block) {
            if ($block->level_number >= $totalLevels) {
                $results['skipped']++;

                continue;
            }

            self::createChargeViaIntake(
                $studentId,
                $semesterId,
                $dueDate,
                (int) $block->level_number,
                [
                    'generation_mode' => SubmitEgcLevelFeeDebitAction::GENERATION_MODE_REISSUE,
                    'block_number' => (int) $block->block_number,
                    'is_retake' => (bool) $block->is_retake,
                ],
                $block,
            );
            $block->update([
                'result' => EgcBlock::RESULT_PENDING,
            ]);

            $created++;
            $results['created']++;
        }

        $results['skipped'] += max(0, $blockCount - $created);
    }

    private static function generateForStudent(
        int $studentId,
        int $semesterId,
        string $dueDate,
        int $blockCount,
        int $currentLevel,
        int $totalLevels,
        int $existingChargeCount,
        array &$results
    ): void {
        $deferredBlocks = EgcBlock::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->get()
            ->filter(fn (EgcBlock $block): bool => app(EgcBlockFinanceResolver::class)->chargeFor($block) === null);

        foreach ($deferredBlocks as $block) {
            self::createChargeViaIntake(
                $studentId,
                $semesterId,
                $dueDate,
                (int) $block->level_number,
                [
                    'generation_mode' => SubmitEgcLevelFeeDebitAction::GENERATION_MODE_DEFERRED,
                    'block_number' => (int) $block->block_number,
                    'is_retake' => (bool) $block->is_retake,
                ],
                $block,
            );
            $results['created']++;
        }

        $existingBlockNumbers = EgcBlock::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->pluck('block_number')
            ->toArray();

        $nextBlockNumber = empty($existingBlockNumbers) ? 1 : (max($existingBlockNumbers) + 1);
        $blocksToCreate = max(0, $blockCount - $existingChargeCount);

        if ($blocksToCreate === 0) {
            $results['skipped'] += $blockCount;
        }

        for ($i = 0; $i < $blocksToCreate; $i++) {
            $blockNumber = $nextBlockNumber + $i;
            $levelNumber = $currentLevel + $i;

            if ($levelNumber >= $totalLevels) {
                $results['skipped']++;

                continue;
            }

            if (in_array($blockNumber, $existingBlockNumbers, true)) {
                $results['skipped']++;

                continue;
            }

            $isRetake = self::isRetakeEligible($studentId, $levelNumber);

            try {
                $block = EgcBlock::create([
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'block_number' => $blockNumber,
                    'level_number' => $levelNumber,
                    'result' => EgcBlock::RESULT_PENDING,
                    'is_retake' => $isRetake,
                ]);

                self::createChargeViaIntake(
                    $studentId,
                    $semesterId,
                    $dueDate,
                    $levelNumber,
                    [
                        'generation_mode' => SubmitEgcLevelFeeDebitAction::GENERATION_MODE_FRESH,
                        'block_number' => $blockNumber,
                        'is_retake' => $isRetake,
                    ],
                    $block,
                );

                $results['created']++;
            } catch (UniqueConstraintViolationException) {
                $results['skipped']++;
            }
        }

        if ($blockCount === 1) {
            $deferredBlockNumber = $nextBlockNumber + 1;
            $deferredLevel = $currentLevel + 1;

            if ($deferredLevel < $totalLevels && ! in_array($deferredBlockNumber, $existingBlockNumbers, true)) {
                try {
                    EgcBlock::create([
                        'student_id' => $studentId,
                        'semester_id' => $semesterId + 1, // next semester placeholder — actual semester set at charge gen time
                        'block_number' => $deferredBlockNumber,
                        'level_number' => $deferredLevel,
                        'result' => EgcBlock::RESULT_PENDING,
                        'is_retake' => false,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // already deferred, skip
                }
            }
        }
    }

    private static function isStudyingLevel(int $studentId, int $levelNumber): bool
    {
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

    private static function isRetakeEligible(int $studentId, int $levelNumber): bool
    {
        return EgcBlock::where('student_id', $studentId)
            ->where('level_number', $levelNumber)
            ->where('result', EgcBlock::RESULT_FAIL)
            ->where('attendance_rate', '>=', 80)
            ->whereNull('retake_discount_id') // entitlement not yet consumed
            ->exists();
    }

    /**
     * Materialize an egc_level_fee debit through the Finance Intake Contract.
     * Generators must not write FinanceCharge rows directly (wave 5 cutover).
     *
     * @param  array{
     *     generation_mode: string,
     *     block_number?: int,
     *     is_retake?: bool,
     * }  $options
     */
    private static function createChargeViaIntake(
        int $studentId,
        int $semesterId,
        string $dueDate,
        int $levelNumber,
        array $options,
        ?EgcBlock $block = null,
    ): FinanceCharge {
        $result = app(SubmitEgcLevelFeeDebitAction::class)->handle(
            $studentId,
            $semesterId,
            $levelNumber,
            [
                'source_kind' => $block === null
                    ? SubmitEgcLevelFeeDebitAction::SOURCE_KIND_BATCH_STUDIO
                    : SubmitEgcLevelFeeDebitAction::SOURCE_KIND_EGC_BLOCK,
                'due_date' => $dueDate,
                'description' => "EGC Level {$levelNumber} Fee",
                'generation_mode' => $options['generation_mode'],
                'block_number' => $options['block_number'] ?? null,
                'is_retake' => (bool) ($options['is_retake'] ?? false),
                'source_ref' => $block === null ? null : app(EgcBlockFinanceResolver::class)->sourceRef($block),
            ],
        );

        $charge = FinanceCharge::query()->find($result->finance_charge_id);

        if (! $charge instanceof FinanceCharge) {
            throw new \RuntimeException(
                "EGC intake materialization incomplete for student {$studentId} level {$levelNumber}."
            );
        }

        return $charge;
    }
}
