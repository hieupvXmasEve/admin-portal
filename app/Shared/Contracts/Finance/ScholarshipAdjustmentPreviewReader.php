<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentPreview;

/**
 * Read-only money projection for a proposed scholarship adjustment.
 *
 * Exists so Academic (which owns the decision UI) can show the fee impact
 * without importing Finance internals, and so the numbers shown come from the
 * same resolver the ledger writes with — no second implementation of the
 * discount math anywhere in the UI layer.
 */
interface ScholarshipAdjustmentPreviewReader
{
    /**
     * @param  float|null  $proposedAmount  The scholarship value that would remain,
     *                                      read in the units of the student's award
     *                                      type (percent or currency). Null previews
     *                                      only the current, unadjusted position.
     */
    public function preview(int $studentId, int $targetSemesterId, ?float $proposedAmount): ScholarshipAdjustmentPreview;
}
