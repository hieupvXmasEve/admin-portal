<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyEgcCarryForwardAction
{
    public function __construct(
        private BuildEgcCarryForwardPlanAction $buildPlanAction,
        private VoidFinanceChargeAction $voidFinanceChargeAction,
    ) {}

    public function run(int $studentId, int $semesterId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($studentId, $semesterId, $userId) {
            $plan = $this->buildPlanAction->run($studentId, $semesterId);

            if ($plan['status'] !== 'eligible') {
                throw ValidationException::withMessages([
                    'student_id' => [$plan['reason']],
                ]);
            }

            $releasedAmount = 0.0;
            $releasedAllocations = 0;
            $voidedChargeIds = [];

            foreach ($plan['unused_charges'] as $unusedCharge) {
                $result = $this->voidFinanceChargeAction->handle(
                    (int) $unusedCharge['id'],
                    'EGC carry-forward release: unused charge not consumed by any mapped EGC block.',
                    $userId,
                    false,
                );

                $releasedAmount += (float) $result['released_amount'];
                $releasedAllocations += (int) $result['released_allocations'];
                $voidedChargeIds[] = (int) $unusedCharge['id'];
            }

            return [
                'student_id' => $studentId,
                'semester_id' => $semesterId,
                'released_amount' => $releasedAmount,
                'released_allocations' => $releasedAllocations,
                'voided_charge_ids' => $voidedChargeIds,
            ];
        });
    }
}
