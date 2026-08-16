<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Actions\Operations\ApplyDeferFinancePolicyAction;
use App\Modules\Finance\Models\DeferCase;
use App\Modules\Finance\Services\DeferCaseService;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Shared\Contracts\Finance\DTO\StudentLifecycleDeferData;
use App\Shared\Contracts\Finance\StudentLifecycleFinanceCommand;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ApplyStudentLifecycleDeferAction implements StudentLifecycleFinanceCommand
{
    public function __construct(
        private readonly DeferCaseService $deferCases,
        private readonly FinanceChargeService $financeCharges,
        private readonly ApplyDeferFinancePolicyAction $applyFinancePolicy,
    ) {}

    public function applyDefer(StudentLifecycleDeferData $data): int
    {
        return DB::transaction(function () use ($data): int {
            if (DeferCase::query()->where('student_action_log_id', $data->studentActionLogId)->exists()) {
                throw new RuntimeException('Defer case already exists for this lifecycle transition.');
            }

            $deferCase = DeferCase::query()->create([
                'student_action_log_id' => $data->studentActionLogId,
                'student_id' => $data->studentId,
                'semester_id' => $data->semesterId,
                'applies_until_semester_id' => $data->appliesUntilSemesterId,
                'scope_type' => $data->scopeType,
                'fee_policy' => $data->feePolicy,
                'applies_once' => true,
                'preserve_amount' => $data->preserveAmount,
                'effective_at' => now(),
                'signed_at' => $data->signedAt,
                'changed_by_user_id' => $data->changedByUserId,
            ]);

            if ($data->scopeType === DeferCase::SCOPE_COURSES && $data->courseRegistrationIds !== []) {
                $this->deferCases->addDeferCaseItems(
                    $deferCase,
                    array_map(
                        static fn (int $registrationId): array => [
                            'course_registration_id' => $registrationId,
                            'fee_policy' => $data->feePolicy,
                        ],
                        $data->courseRegistrationIds,
                    ),
                );
            }

            if ($data->scopeType === DeferCase::SCOPE_FULL) {
                $this->deferCases->itemizeFullScope($deferCase);
            }

            $this->deferCases->processFeePolicy($deferCase);

            if ($data->isEgcDefer) {
                $this->financeCharges->createEgcDeferCredits(
                    studentId: $data->studentId,
                    fromSemesterId: $data->semesterId,
                    chargeIds: $data->egcChargeIds,
                    effectiveAt: $deferCase->effective_at,
                    userId: $data->changedByUserId,
                );
            } else {
                $this->applyFinancePolicy->handle($deferCase, $data->changedByUserId);
            }

            return (int) $deferCase->id;
        });
    }
}
