<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\CourseRegistration;
use App\Models\DeferCase;
use App\Models\FinanceCharge;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\DeferBackfillClassification as C;
use Illuminate\Support\Collection;

/**
 * FIN-REV-020 — read-only classifier for the full-scope defer backfill.
 *
 * For every full-scope defer case it reports whether item-level course
 * evidence is still missing (itemization status) and surfaces finance flags
 * the money-settlement phase needs. This query performs SELECTs only and never
 * mutates data.
 */
class ClassifyDeferBackfillCandidatesQuery
{
    /** Active enrollment statuses mirror CourseRegistration::scopeActive. */
    private const ACTIVE_STATUSES = ['pending', 'registered', 'confirmed'];

    public function __construct(
        private readonly SettlementService $settlementService,
    ) {}

    /**
     * @return array{cases: array<int, array<string, mixed>>, counts: array<string, int>}
     */
    public function handle(?int $semesterId = null): array
    {
        $cases = DeferCase::query()
            ->where('scope_type', DeferCase::SCOPE_FULL)
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->with('items:id,defer_case_id,course_registration_id')
            ->orderBy('id')
            ->get()
            ->map(fn (DeferCase $deferCase): array => $this->classify($deferCase))
            ->all();

        return [
            'cases' => $cases,
            'counts' => $this->aggregate($cases),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function classify(DeferCase $deferCase): array
    {
        $studentId = (int) $deferCase->student_id;
        $semesterId = (int) $deferCase->semester_id;

        $activeRegistrationIds = CourseRegistration::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereIn('registration_status', self::ACTIVE_STATUSES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $itemizedRegistrationIds = $deferCase->items
            ->pluck('course_registration_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missingRegistrationIds = array_values(array_diff($activeRegistrationIds, $itemizedRegistrationIds));

        $itemization = match (true) {
            ! empty($missingRegistrationIds) => C::ITEMIZATION_ITEMIZABLE,
            ! empty($itemizedRegistrationIds) => C::ITEMIZATION_ALREADY_ITEMIZED,
            default => C::ITEMIZATION_NO_REGISTRATION,
        };

        $financeFlags = $this->resolveFinanceFlags($deferCase, $studentId, $semesterId);

        $reviewRequired = $itemization === C::ITEMIZATION_NO_REGISTRATION
            || in_array(C::FLAG_AMBIGUOUS_PARTIAL, $financeFlags, true);

        return [
            'defer_case_id' => (int) $deferCase->id,
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'fee_policy' => $deferCase->fee_policy,
            'itemization' => $itemization,
            'active_registration_count' => count($activeRegistrationIds),
            'planned_item_count' => count($missingRegistrationIds),
            'finance_flags' => $financeFlags,
            'review_required' => $reviewRequired,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveFinanceFlags(DeferCase $deferCase, int $studentId, int $semesterId): array
    {
        $charges = FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->get(['id', 'status']);

        $activeCharges = $charges->where('status', FinanceCharge::STATUS_ACTIVE);
        $hasVoided = $charges->where('status', FinanceCharge::STATUS_VOID)->isNotEmpty();

        $flags = [];

        if ($activeCharges->isNotEmpty()) {
            $flags[] = C::FLAG_HAS_ACTIVE_CHARGE;
        } elseif ($hasVoided) {
            $flags[] = C::FLAG_CHARGE_VOIDED;
        } else {
            $flags[] = C::FLAG_NO_CHARGE;
        }

        if ($deferCase->fee_policy === DeferCase::POLICY_PARTIAL && $this->isPartialAmbiguous($deferCase, $activeCharges)) {
            $flags[] = C::FLAG_AMBIGUOUS_PARTIAL;
        }

        return $flags;
    }

    /**
     * A PARTIAL case is ambiguous when no preserve amount is recorded or it
     * exceeds the cash actually paid against the active charges.
     *
     * @param  Collection<int, FinanceCharge>  $activeCharges
     */
    private function isPartialAmbiguous(DeferCase $deferCase, $activeCharges): bool
    {
        if ($deferCase->preserve_amount === null) {
            return true;
        }

        $paid = $activeCharges->sum(
            fn (FinanceCharge $charge): float => $this->settlementService->getChargePaidAmount((int) $charge->id)
        );

        return (float) $deferCase->preserve_amount > (float) $paid;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cases
     * @return array<string, int>
     */
    private function aggregate(array $cases): array
    {
        $counts = [
            'total' => count($cases),
            'itemizable' => 0,
            'already_itemized' => 0,
            'no_registration' => 0,
            'needs_review' => 0,
            'planned_items' => 0,
        ];

        foreach ($cases as $case) {
            $counts[$case['itemization']]++;
            $counts['planned_items'] += $case['planned_item_count'];

            if ($case['review_required']) {
                $counts['needs_review']++;
            }
        }

        return $counts;
    }
}
