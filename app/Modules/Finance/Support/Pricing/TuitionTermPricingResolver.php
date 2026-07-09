<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Pricing;

use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use RuntimeException;

/**
 * Finance-owned tuition pricing from TuitionPlan / TuitionPlanTerm.
 *
 * Sources send pricing facts only (curriculum_version_id, intake_semester_id,
 * term_number). Final amount is never accepted from the caller.
 */
final class TuitionTermPricingResolver
{
    /**
     * @return array{amount: float, currency: string, rule_version: string, snapshot: array<string, mixed>}
     */
    public function price(FinanceIntakeData $intake): array
    {
        $curriculumVersionId = $this->requiredIntFact($intake, 'curriculum_version_id');
        $intakeSemesterId = $this->requiredIntFact($intake, 'intake_semester_id');
        $termNumber = $this->requiredIntFact($intake, 'term_number');

        $plan = TuitionPlan::query()
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('intake_semester_id', $intakeSemesterId)
            ->where('is_active', true)
            ->first();

        if (! $plan instanceof TuitionPlan) {
            throw new RuntimeException(
                "No active tuition plan for curriculum_version_id={$curriculumVersionId}, intake_semester_id={$intakeSemesterId}."
            );
        }

        $term = TuitionPlanTerm::query()
            ->where('tuition_plan_id', $plan->id)
            ->where('term_number', $termNumber)
            ->first();

        if (! $term instanceof TuitionPlanTerm) {
            throw new RuntimeException(
                "No tuition plan term {$termNumber} on plan {$plan->id}."
            );
        }

        $amount = (float) $term->amount;
        $currency = $plan->currency ?: 'VND';
        $ruleVersion = sprintf(
            'tuition_plan:%d:term:%d:updated:%s',
            $plan->id,
            $termNumber,
            optional($term->updated_at)?->format('YmdHis') ?? '0',
        );

        return [
            'amount' => $amount,
            'currency' => $currency,
            'rule_version' => $ruleVersion,
            'snapshot' => [
                'pricing_source' => 'tuition_plan',
                'tuition_plan_id' => $plan->id,
                'tuition_plan_term_id' => $term->id,
                'curriculum_version_id' => $curriculumVersionId,
                'intake_semester_id' => $intakeSemesterId,
                'term_number' => $termNumber,
                'amount' => $amount,
                'currency' => $currency,
                'obligation_type' => $intake->obligation_type,
                'facts' => $intake->facts,
            ],
        ];
    }

    private function requiredIntFact(FinanceIntakeData $intake, string $key): int
    {
        if (! array_key_exists($key, $intake->facts)) {
            throw InvalidFinanceIntakePayload::missingFact($key);
        }

        return (int) $intake->facts[$key];
    }
}
