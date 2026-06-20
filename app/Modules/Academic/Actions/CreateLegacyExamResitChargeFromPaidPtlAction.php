<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\FinanceCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Create a legacy `exam_resit_fee` FinanceCharge for paid PTL (thi lại) DNG requests
 * that predate the charge link, then point the DNG request at the new charge.
 *
 * Typical case: the student paid through DNG before Academic created an
 * ExamResitAttempt/charge, so payment bridged to unrelated charges (e.g. tuition).
 * Reconciliation can then link the charge to an Academic source; paid state may be
 * derived from the linked paid DNG when FinanceCharge::is_fully_paid is false.
 */
class CreateLegacyExamResitChargeFromPaidPtlAction
{
    private const PTL = 'PTL';

    private const PAID_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    /** Default resit unit when the DNG description does not name one. */
    private const DEFAULT_RESIT_UNIT_CODE = 'TEC002';

    public function __construct(
        private readonly CreateFinanceChargeAction $createChargeAction,
    ) {}

    /**
     * @return array{checked:int,created:int,skipped:int,details:array<int,array<string,mixed>>}
     */
    public function run(bool $dryRun = false): array
    {
        $result = [
            'checked' => 0,
            'created' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        foreach ($this->unlinkedPaidPtlRequests() as $request) {
            $result['checked']++;

            if ($this->studentHasActiveExamResitCharge((int) $request->student_id)) {
                $result['skipped']++;
                $result['details'][] = [
                    'dng_payment_request_id' => $request->id,
                    'student_id' => $request->student_id,
                    'status' => 'skipped',
                    'reason' => 'active_exam_resit_fee_exists',
                ];

                continue;
            }

            if ($dryRun) {
                $result['created']++;
                $result['details'][] = [
                    'dng_payment_request_id' => $request->id,
                    'student_id' => $request->student_id,
                    'status' => 'created',
                    'amount' => (float) $request->amount,
                    'description' => $this->chargeDescription($request),
                ];

                continue;
            }

            $detail = DB::transaction(function () use ($request): array {
                $locked = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);

                if ($this->studentHasActiveExamResitCharge((int) $locked->student_id)) {
                    return [
                        'dng_payment_request_id' => $locked->id,
                        'student_id' => $locked->student_id,
                        'status' => 'skipped',
                        'reason' => 'active_exam_resit_fee_exists',
                    ];
                }

                $charge = $this->createChargeAction->handle([
                    'student_id' => $locked->student_id,
                    'semester_id' => $locked->semester_id,
                    'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
                    'amount' => (float) $locked->amount,
                    'description' => $this->chargeDescription($locked),
                    'created_by_user_id' => auth()->id(),
                ]);

                $locked->update(['finance_charge_id' => $charge->id]);

                return [
                    'dng_payment_request_id' => $locked->id,
                    'student_id' => $locked->student_id,
                    'status' => 'created',
                    'finance_charge_id' => $charge->id,
                    'amount' => (float) $charge->amount,
                    'description' => $charge->description,
                ];
            });

            if (($detail['status'] ?? null) === 'created') {
                $result['created']++;
            } else {
                $result['skipped']++;
            }

            $result['details'][] = $detail;
        }

        return $result;
    }

    /**
     * @return Collection<int, DngPaymentRequest>
     */
    private function unlinkedPaidPtlRequests(): Collection
    {
        return DngPaymentRequest::query()
            ->where('fee_type', self::PTL)
            ->whereIn('status', self::PAID_STATUSES)
            ->whereNull('finance_charge_id')
            ->orderBy('id')
            ->get();
    }

    private function studentHasActiveExamResitCharge(int $studentId): bool
    {
        return FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('charge_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->exists();
    }

    private function chargeDescription(DngPaymentRequest $request): string
    {
        $description = trim((string) $request->description);

        if ($description !== '' && preg_match('/\bTEC00[12]\b/i', $description) === 1) {
            return $description;
        }

        return 'Exam Retake Fee: '.self::DEFAULT_RESIT_UNIT_CODE;
    }
}