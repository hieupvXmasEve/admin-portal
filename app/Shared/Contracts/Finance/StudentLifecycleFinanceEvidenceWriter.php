<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

interface StudentLifecycleFinanceEvidenceWriter
{
    public function attachDeferEvidence(int $studentActionLogId, int $uploadRecordId): void;
}
