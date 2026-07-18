<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\DeferCase;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceEvidenceWriter;

final class AttachStudentLifecycleFinanceEvidenceAction implements StudentLifecycleFinanceEvidenceWriter
{
    public function attachDeferEvidence(int $studentActionLogId, int $uploadRecordId): void
    {
        DeferCase::query()
            ->where('student_action_log_id', $studentActionLogId)
            ->update(['upload_record_id' => $uploadRecordId]);
    }
}
