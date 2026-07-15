<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;
use App\Shared\Contracts\Academic\DTO\AcademicExamResitDueData;

/**
 * Resolve the linkage between Finance DNG payment requests and Academic
 * exam-resit (thi lại / PTL) sources for the Due Reminders worklist
 * (ACAD-RET-002).
 *
 * A DNG request links to Finance charges through exact allocation rows. An
 * exam-resit attempt resolves from its charge's canonical Finance obligation.
 * All lookups are batched to keep the page query N+1-free.
 */
class ExamResitDngLinkResolver
{
    public function __construct(
        private readonly ?AcademicFinanceChargeSourceGateway $academicSources = null,
    ) {}

    /**
     * Map each PTL/exam-resit DNG request id to its linked exam-resit source,
     * with the relations the worklist row needs eager-loaded.
     *
     * @param  iterable<DngPaymentRequest>  $requests
     * @return array<int, AcademicExamResitDueData>
     */
    public function attemptsByDngRequest(iterable $requests): array
    {
        $chargeIdsByRequest = [];
        $allChargeIds = [];

        foreach ($requests as $request) {
            $chargeIds = $this->chargeIdsForRequest($request);
            if ($chargeIds === []) {
                continue;
            }
            $chargeIdsByRequest[$request->id] = $chargeIds;
            $allChargeIds = array_merge($allChargeIds, $chargeIds);
        }

        $allChargeIds = array_values(array_unique($allChargeIds));
        if ($allChargeIds === []) {
            return [];
        }

        $attemptsByCharge = $this->attemptsByChargeId($allChargeIds);

        $result = [];
        foreach ($chargeIdsByRequest as $requestId => $chargeIds) {
            foreach ($chargeIds as $chargeId) {
                if (isset($attemptsByCharge[$chargeId])) {
                    $result[$requestId] = $attemptsByCharge[$chargeId];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Of the given FinanceCharge ids, those that currently have an active pushed
     * DNG request through an exact allocation row.
     *
     * @param  array<int>  $chargeIds
     * @return array<int, int> list of charge ids with a pushed DNG
     */
    public function chargeIdsWithActivePushedDng(array $chargeIds): array
    {
        $chargeIds = array_values(array_unique(array_filter($chargeIds)));
        if ($chargeIds === []) {
            return [];
        }

        $pivot = DngPaymentRequestCharge::query()
            ->whereIn('finance_charge_id', $chargeIds)
            ->whereHas('dngPaymentRequest', fn ($query) => $query->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG))
            ->pluck('finance_charge_id')
            ->all();

        return array_values(array_unique(array_map('intval', $pivot)));
    }

    /**
     * Mirror a successful DNG reminder onto every exam-resit attempt the request
     * collects, so Academic sees the same `last_reminded_at`. Returns the number
     * of attempts touched. Never changes recipient selection or delivery.
     */
    public function touchLinkedAttempts(DngPaymentRequest $request, \DateTimeInterface $timestamp): int
    {
        $request->loadMissing('chargeLinks');
        $chargeIds = $this->chargeIdsForRequest($request);

        if ($chargeIds === []) {
            return 0;
        }

        $attemptIds = array_map(
            static fn (AcademicExamResitDueData $attempt): int => $attempt->id,
            $this->attemptsByChargeId($chargeIds),
        );

        return $this->academicSources()->touchExamResitDueSources($attemptIds, $timestamp);
    }

    /**
     * @return array<int, int> charge ids attached to this DNG request
     */
    private function chargeIdsForRequest(DngPaymentRequest $request): array
    {
        $ids = [];

        // chargeLinks is eager-loaded by the page query; ?? guards lazy access.
        foreach ($request->chargeLinks ?? [] as $link) {
            if ($link->finance_charge_id !== null) {
                $ids[] = (int) $link->finance_charge_id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int>  $chargeIds
     * @return array<int, AcademicExamResitDueData> keyed by Finance charge id
     */
    private function attemptsByChargeId(array $chargeIds): array
    {
        $attemptIdByCharge = FinanceCharge::query()
            ->whereIn('finance_charges.id', $chargeIds)
            ->whereNotNull('finance_charges.finance_obligation_id')
            ->join('finance_obligations', 'finance_obligations.id', '=', 'finance_charges.finance_obligation_id')
            ->where('finance_obligations.source_system', AcademicFinanceSourceKeys::SOURCE_SYSTEM)
            ->where('finance_obligations.source_kind', AcademicFinanceSourceKeys::EXAM_RESIT_ATTEMPT)
            ->pluck('finance_obligations.source_ref', 'finance_charges.id')
            ->map(static fn (string $sourceRef): int => AcademicFinanceSourceKeys::sourceIdFromRef($sourceRef, 'exam-resit:') ?? 0)
            ->filter(static fn (int $attemptId): bool => $attemptId > 0);

        $attempts = $this->academicSources()->examResitDueSourcesByIds($attemptIdByCharge->values()->all());

        return $attemptIdByCharge
            ->map(fn (int $attemptId) => $attempts[$attemptId] ?? null)
            ->filter()
            ->all();
    }

    private function academicSources(): AcademicFinanceChargeSourceGateway
    {
        return $this->academicSources ?? app(AcademicFinanceChargeSourceGateway::class);
    }
}
