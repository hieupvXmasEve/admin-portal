<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\DeferCase;
use App\Modules\Finance\Queries\Operations\ClassifyDeferBackfillCandidatesQuery;
use App\Modules\Finance\Services\DeferCaseService;
use App\Modules\Finance\Support\DeferBackfillClassification as C;
use Illuminate\Support\Facades\DB;

/**
 * FIN-REV-020 — backfill item-level evidence (and optionally money) for legacy
 * full-scope defer cases.
 *
 * Dry-run (default) only classifies and reports; it never writes. With --apply
 * it creates the missing defer_case_items and marks the registrations
 * non-billable. With --apply-money (FIN-REV-020-03 / M3) it also settles the
 * finance policy for each full-scope case by reusing ApplyDeferFinancePolicyAction,
 * which self-gates needs-review cases (live DNG, discount, non-FULL/PARTIAL
 * scope) and is idempotent (an already-settled case has no active obligation).
 */
class BackfillDeferCaseItemsAction
{
    public function __construct(
        private readonly ClassifyDeferBackfillCandidatesQuery $classifier,
        private readonly DeferCaseService $deferCaseService,
        private readonly ApplyDeferFinancePolicyAction $applyPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(bool $apply = false, ?int $semesterId = null, bool $applyMoney = false): array
    {
        $classification = $this->classifier->handle($semesterId);
        $plannedItemCount = $classification['counts']['planned_items'];

        $itemsCreated = 0;
        $casesItemized = 0;

        if ($apply) {
            foreach ($classification['cases'] as $case) {
                if ($case['itemization'] !== C::ITEMIZATION_ITEMIZABLE) {
                    continue;
                }

                $created = DB::transaction(function () use ($case) {
                    $deferCase = DeferCase::findOrFail($case['defer_case_id']);

                    return $this->deferCaseService->itemizeFullScope($deferCase)->count();
                });

                if ($created > 0) {
                    $itemsCreated += $created;
                    $casesItemized++;
                }
            }
        }

        return [
            'mode' => $apply ? 'apply' : 'dry-run',
            'apply_money' => $applyMoney,
            'counts' => $classification['counts'],
            'cases' => $classification['cases'],
            'planned_item_count' => $plannedItemCount,
            'items_created' => $itemsCreated,
            // one item is created per registration, so each item marks one registration 'defer'
            'registrations_marked' => $itemsCreated,
            'cases_itemized' => $casesItemized,
            'money' => $this->settleMoney($applyMoney, $classification['cases']),
        ];
    }

    /**
     * Settle the finance policy for every full-scope case via the M1 action.
     *
     * The action is authoritative for safety: it self-gates needs-review cases
     * (live DNG / discount / non-FULL/PARTIAL scope → skipped) and no-charge
     * cases (noop), and is idempotent on re-run. Each settlement runs in its own
     * transaction so one failure cannot roll back already-settled cases.
     *
     * @param  array<int, array<string, mixed>>  $cases
     * @return array<string, mixed>
     */
    private function settleMoney(bool $applyMoney, array $cases): array
    {
        $summary = [
            'applied' => $applyMoney,
            'settled' => 0,
            'skipped' => 0,
            'noop' => 0,
            'released' => 0.0,
            'consumed' => 0.0,
            'by_reason' => [],
        ];

        if (! $applyMoney) {
            return $summary;
        }

        foreach ($cases as $case) {
            $result = DB::transaction(function () use ($case): array {
                $deferCase = DeferCase::findOrFail($case['defer_case_id']);

                return $this->applyPolicy->handle($deferCase);
            });

            $bucket = match ($result['status']) {
                ApplyDeferFinancePolicyAction::STATUS_APPLIED => 'settled',
                ApplyDeferFinancePolicyAction::STATUS_SKIPPED => 'skipped',
                default => 'noop',
            };

            $summary[$bucket]++;
            $summary['released'] += (float) $result['released'];
            $summary['consumed'] += (float) $result['consumed'];
            $summary['by_reason'][$result['reason']] = ($summary['by_reason'][$result['reason']] ?? 0) + 1;
        }

        return $summary;
    }
}
