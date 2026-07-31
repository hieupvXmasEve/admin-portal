<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentResult;

/**
 * Finance intake for an approved per-semester scholarship adjustment.
 *
 * Ordering rule for callers (Academic): call Finance FIRST, then write the
 * dossier status from the returned outcome — the approval must never be
 * rolled back by a ledger refusal, so this call never throws for ledger
 * conditions (they surface as finance_review_required).
 */
interface ScholarshipAdjustmentContract
{
    public function apply(ScholarshipAdjustmentData $data): ScholarshipAdjustmentResult;
}
