<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\StudentActionLog;
use App\Modules\Finance\Services\FinanceChargeService;

class StudentActionPreservePreviewResolver
{
    public function __construct(private readonly FinanceChargeService $financeChargeService) {}

    public function resolve(array $payload, ?int $existingActionLogId = null): ?array
    {
        if (($payload['action_type'] ?? null) !== 'ACADEMIC_DEFER') {
            return null;
        }

        if (($payload['defer_fee_policy'] ?? null) !== 'PRESERVE') {
            return null;
        }

        if ($existingActionLogId) {
            $actionLog = StudentActionLog::query()
                ->with('deferCase:id,student_action_log_id,preserve_amount,fee_policy,semester_id')
                ->find($existingActionLogId);

            if ($actionLog?->deferCase) {
                return [
                    'source' => 'existing_defer_case',
                    'preserve_amount' => (float) ($actionLog->deferCase->preserve_amount ?? 0),
                    'fee_policy' => $actionLog->deferCase->fee_policy,
                ];
            }
        }

        $studentId = (int) ($payload['student_id'] ?? 0);
        $semesterId = (int) ($payload['from_semester_id'] ?? 0);

        if ($studentId <= 0 || $semesterId <= 0) {
            return null;
        }

        return [
            'source' => 'estimated_from_active_charges',
            'preserve_amount' => (float) $this->financeChargeService->getTotalCharges($studentId, $semesterId),
            'fee_policy' => 'PRESERVE',
        ];
    }
}
