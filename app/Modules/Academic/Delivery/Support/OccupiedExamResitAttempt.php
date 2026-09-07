<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\ExamResitAttempt;
use Illuminate\Database\Eloquent\Builder;

/**
 * A resit that still occupies its academic record for remediation routing:
 * hide that record from học lại, and do not open another thi lại, until the
 * source is cancelled, completed, or no-show.
 */
final class OccupiedExamResitAttempt
{
    public const STATUSES = [
        ExamResitAttempt::STATUS_REQUESTED,
        ExamResitAttempt::STATUS_APPROVED,
        ExamResitAttempt::STATUS_SCHEDULED,
        ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION,
    ];

    /**
     * @param  Builder<ExamResitAttempt>  $query
     * @return Builder<ExamResitAttempt>
     */
    public static function constrain(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUSES);
    }
}
