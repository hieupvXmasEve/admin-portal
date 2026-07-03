<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\EgcBlock;
use App\Models\Semester;
use Illuminate\Support\Collection;

final class EgcRetakeTargetResolver
{
    /**
     * Resolve the one block that may receive the retake relevel/discount.
     *
     * Block 1 failures target block 2 in the same semester. Block 2 failures
     * target block 1 in the immediately following semester. No later fallback.
     *
     * @return Collection<int, EgcBlock>
     */
    public static function targetBlocksFor(EgcBlock $sourceBlock): Collection
    {
        $query = EgcBlock::query()
            ->where('student_id', $sourceBlock->student_id)
            ->where('result', EgcBlock::RESULT_PENDING)
            ->whereNotNull('finance_charge_id')
            ->whereKeyNot($sourceBlock->id);

        if ((int) $sourceBlock->block_number === 1) {
            return $query
                ->where('semester_id', $sourceBlock->semester_id)
                ->where('block_number', 2)
                ->orderBy('id')
                ->get();
        }

        if ((int) $sourceBlock->block_number !== 2) {
            return EgcBlock::query()->whereRaw('1 = 0')->get();
        }

        $nextSemesterId = self::nextSemesterId($sourceBlock);
        if ($nextSemesterId === null) {
            return EgcBlock::query()->whereRaw('1 = 0')->get();
        }

        return $query
            ->where('semester_id', $nextSemesterId)
            ->where('block_number', 1)
            ->orderBy('id')
            ->get();
    }

    private static function nextSemesterId(EgcBlock $sourceBlock): ?int
    {
        $sourceSemester = Semester::query()->find($sourceBlock->semester_id);
        if (! $sourceSemester instanceof Semester || $sourceSemester->start_date === null) {
            return null;
        }

        $nextId = Semester::query()
            ->where('id', '!=', $sourceSemester->id)
            ->where('is_archived', false)
            ->where('start_date', '>', $sourceSemester->start_date)
            ->orderBy('start_date')
            ->orderBy('id')
            ->value('id');

        return $nextId !== null ? (int) $nextId : null;
    }
}
