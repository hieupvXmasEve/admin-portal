<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Finance\TuitionChargeExistenceReader;

/**
 * Implementation for App\Shared\Contracts\Finance\TuitionChargeExistenceReader.
 * Single set-based query — Academic calls this once per candidate run, not
 * per student. Predicate mirrors PreviewMajorChargeGenerationQuery's
 * "already_charged" check so candidate exclusion and preview agree.
 */
class TuitionChargeExistenceQuery implements TuitionChargeExistenceReader
{
    public function tuitionTermChargedByStudent(array $studentIds, int $semesterId): array
    {
        $map = array_fill_keys($studentIds, false);

        if ($studentIds === []) {
            return $map;
        }

        $chargedIds = FinanceCharge::query()
            ->whereIn('student_id', $studentIds)
            ->where('semester_id', $semesterId)
            ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->pluck('student_id');

        foreach ($chargedIds as $studentId) {
            $map[$studentId] = true;
        }

        return $map;
    }
}
