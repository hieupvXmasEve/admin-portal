<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\StudentLifecycleFinanceCharge;
use App\Shared\Contracts\Finance\DTO\StudentLifecyclePreservePreview;

interface StudentLifecycleFinanceReader
{
    public function preservePreview(
        int $studentId,
        int $semesterId,
        ?int $existingActionLogId = null,
    ): StudentLifecyclePreservePreview;

    /** @return list<StudentLifecycleFinanceCharge> */
    public function activeEgcCharges(int $studentId, ?int $semesterId = null): array;

    public function egcPreserveValidationError(int $chargeId, int $studentId, ?int $semesterId): ?string;

    /** @return list<int> */
    public function deferredCourseRegistrationIds(int $studentId, int $semesterId): array;
}
