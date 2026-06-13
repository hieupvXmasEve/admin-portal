<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Database\Eloquent\Builder;

final class LifecycleDueItemPredicate
{
    public static function isLifecycleException(?Student $student): bool
    {
        if ($student === null) {
            return true;
        }

        return ! in_array($student->status, Student::FINANCIAL_STATUSES, true);
    }

    /**
     * @param  Builder<DngPaymentRequest>  $query
     */
    public static function applyActiveCollectionScope(Builder $query): void
    {
        $query->whereHas('student', function (Builder $studentQuery): void {
            $studentQuery->whereIn('status', Student::FINANCIAL_STATUSES);
        });
    }

    /**
     * @param  Builder<DngPaymentRequest>  $query
     */
    public static function applyLifecycleExceptionScope(Builder $query): void
    {
        $query->where(function (Builder $exceptionQuery): void {
            $exceptionQuery
                ->whereNull('student_id')
                ->orWhereDoesntHave('student')
                ->orWhereHas('student', function (Builder $studentQuery): void {
                    $studentQuery->whereNotIn('status', Student::FINANCIAL_STATUSES);
                });
        });
    }

    public static function isOpenDueRequest(DngPaymentRequest $request): bool
    {
        return $request->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG
            && $request->due_date !== null;
    }
}
