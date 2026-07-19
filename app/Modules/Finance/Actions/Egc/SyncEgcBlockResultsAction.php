<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\AcademicEgcBlockData;
use App\Shared\Contracts\Finance\EgcBlockResultReconciler;

class SyncEgcBlockResultsAction implements EgcBlockResultReconciler
{
    /**
     * Sync EGC block results from academic_records for a given semester.
     *
     * Resolution rules:
     * - override_pass = true → result = pass
     * - else: latest is_passed by recorded_at DESC
     * - attendance_percentage from the resolved record
     */
    public static function run(int $semesterId): array
    {
        return app(self::class)->syncSemester($semesterId);
    }

    public function reconcileSemester(int $semesterId): void
    {
        $this->syncSemester($semesterId);
    }

    /**
     * @return array{
     *     synced: int,
     *     total: int,
     *     skipped: list<array<string, mixed>>,
     *     reconciliation: array<string, mixed>
     * }
     */
    private function syncSemester(int $semesterId): array
    {
        $academicSources = app(AcademicFinanceChargeSourceGateway::class);
        $blocks = collect($academicSources->egcBlocksForSemester($semesterId));

        $synced = 0;
        $skipped = [];

        foreach ($blocks->groupBy('student_id') as $studentId => $studentBlocks) {
            $resolvedByBlock = $academicSources->resolveEgcBlockResultsByBlockIds(
                $studentBlocks->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()
            );

            foreach ($studentBlocks as $block) {
                $resolved = $resolvedByBlock[$block->id] ?? null;

                if ($resolved === null) {
                    continue;
                }

                if (
                    $resolved['result'] === AcademicEgcBlockData::RESULT_FAIL
                    && $academicSources->hasImmediateNextEgcBlockProgressionEvidence((int) $block->id)
                ) {
                    $skipped[] = self::skippedRow($block, 'failed_result_conflicts_with_later_egc_progression');

                    continue;
                }

                $academicSources->updateEgcBlockResult(
                    (int) $block->id,
                    $resolved['result'],
                    $resolved['attendance_rate'],
                );

                $synced++;
            }
        }

        return [
            'synced' => $synced,
            'total' => $blocks->count(),
            'skipped' => $skipped,
            'reconciliation' => ReconcileEgcChargesAfterSyncAction::run($semesterId),
        ];
    }

    /**
     * Resolve the block result for a student + semester + level from academic_records.
     * Also used by backfill command.
     *
     * @return array{result: string, attendance_rate: float|null}|null
     */
    public static function resolveResult(int $studentId, int $semesterId, int $levelNumber): ?array
    {
        return app(AcademicFinanceChargeSourceGateway::class)->resolveEgcBlockResult($studentId, $semesterId, $levelNumber);
    }

    private static function skippedRow(AcademicEgcBlockData $block, string $reason): array
    {
        return [
            'student_id' => (int) $block->student_id,
            'source_egc_block_id' => (int) $block->id,
            'block_number' => (int) $block->block_number,
            'level_number' => (int) $block->level_number,
            'reason' => $reason,
        ];
    }
}
