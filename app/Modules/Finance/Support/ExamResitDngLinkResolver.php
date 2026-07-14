<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\ExamResitAttempt;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;

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
    /**
     * Map each PTL/exam-resit DNG request id to its linked ExamResitAttempt,
     * with the relations the worklist row needs eager-loaded.
     *
     * @param  iterable<DngPaymentRequest>  $requests
     * @return array<int, ExamResitAttempt>
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

        return ExamResitAttempt::query()
            ->whereIn('id', array_keys($this->attemptsByChargeId($chargeIds)))
            ->update(['last_reminded_at' => $timestamp]);
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
     * @return array<int, ExamResitAttempt> keyed by Finance charge id
     */
    private function attemptsByChargeId(array $chargeIds): array
    {
        $attemptIdByCharge = FinanceCharge::query()
            ->whereIn('finance_charges.id', $chargeIds)
            ->whereNotNull('finance_charges.finance_obligation_id')
            ->join('finance_obligations', 'finance_obligations.id', '=', 'finance_charges.finance_obligation_id')
            ->where('finance_obligations.source_system', 'academic')
            ->where('finance_obligations.source_kind', 'exam_resit_attempt')
            ->pluck('finance_obligations.source_ref', 'finance_charges.id')
            ->map(static fn (string $sourceRef): int => str_starts_with($sourceRef, 'exam-resit:')
                ? (int) substr($sourceRef, strlen('exam-resit:'))
                : 0)
            ->filter(static fn (int $attemptId): bool => $attemptId > 0);

        $attempts = ExamResitAttempt::query()
            ->whereIn('id', $attemptIdByCharge->values())
            ->with([
                'student:id,student_id,full_name,email,status',
                'unit:id,code,name',
                'session:id,exam_room_slot_id,unit_id,status',
                'session.roomSlot:id,room_id,exam_date,start_time,end_time',
                'session.roomSlot.room:id,name,code',
            ])
            ->get()
            ->keyBy('id');

        return $attemptIdByCharge
            ->map(fn (int $attemptId) => $attempts->get($attemptId))
            ->filter()
            ->all();
    }
}
