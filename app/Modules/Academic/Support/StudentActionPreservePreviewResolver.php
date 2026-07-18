<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Shared\Contracts\Finance\StudentLifecycleFinanceReader;

class StudentActionPreservePreviewResolver
{
    public function __construct(private readonly StudentLifecycleFinanceReader $finance) {}

    public function resolve(array $payload, ?int $existingActionLogId = null): ?array
    {
        if (($payload['action_type'] ?? null) !== 'ACADEMIC_DEFER') {
            return null;
        }

        if (($payload['defer_fee_policy'] ?? null) !== 'PRESERVE') {
            return null;
        }

        $studentId = (int) ($payload['student_id'] ?? 0);
        $semesterId = (int) ($payload['from_semester_id'] ?? 0);

        if ($studentId <= 0 || $semesterId <= 0) {
            return null;
        }

        return $this->finance
            ->preservePreview($studentId, $semesterId, $existingActionLogId)
            ->toArray();
    }
}
