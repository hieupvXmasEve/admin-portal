<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Egc;

use App\Modules\Finance\Actions\Egc\BuildEgcCarryForwardPlanAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

class ListEgcCarryForwardCandidatesQuery
{
    public function __construct(
        private BuildEgcCarryForwardPlanAction $buildPlanAction,
    ) {}

    public function handle(int $semesterId, ?int $campusId = null): array
    {
        $studentIds = FinanceCharge::query()
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('semester_id', $semesterId)
            ->where('amount', '>', 0)
            ->distinct()
            ->pluck('student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->all();
        $references = app(StudentReferenceReader::class)->findMany($studentIds);

        $groups = [
            'eligible' => [],
            'needs_data_repair' => [],
            'ineligible' => [],
        ];

        foreach ($references as $studentId => $reference) {
            if ($campusId !== null && $reference->campusId !== $campusId) {
                continue;
            }

            $candidate = $this->buildPlanAction->run((int) $studentId, $semesterId);
            $groups[$candidate['status']][] = $candidate;
        }

        return $groups;
    }
}
