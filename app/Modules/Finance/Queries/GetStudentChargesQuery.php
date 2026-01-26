<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Models\FinanceCharge;
use Illuminate\Support\Collection;

class GetStudentChargesQuery
{
    /**
     * Get active charges for a student in a semester.
     */
    public function handle(int $studentId, ?int $semesterId = null): Collection
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return $query->orderBy('effective_at')->get();
    }
}
