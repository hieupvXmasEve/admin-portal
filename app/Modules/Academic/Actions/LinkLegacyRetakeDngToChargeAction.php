<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Point paid HL (học lại) DNG requests at the student's existing retake_fee charge
 * and sync registration payment state when the fee was already paid historically.
 */
class LinkLegacyRetakeDngToChargeAction
{
    private const HL = 'HL';

    private const PAID_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    /**
     * @return array{checked:int,linked:int,skipped:int,paid_synced:int,details:array<int,array<string,mixed>>}
     */
    public function run(bool $dryRun = false): array
    {
        $result = [
            'checked' => 0,
            'linked' => 0,
            'skipped' => 0,
            'paid_synced' => 0,
            'details' => [],
        ];

        foreach ($this->unlinkedPaidHlRequests() as $request) {
            $result['checked']++;

            $chargeId = $this->resolveRetakeChargeId((int) $request->student_id);
            if ($chargeId === null) {
                $result['skipped']++;
                $result['details'][] = [
                    'dng_payment_request_id' => $request->id,
                    'student_id' => $request->student_id,
                    'status' => 'skipped',
                    'reason' => 'no_retake_fee_charge',
                ];

                continue;
            }

            if ($dryRun) {
                $result['linked']++;
                $result['details'][] = [
                    'dng_payment_request_id' => $request->id,
                    'student_id' => $request->student_id,
                    'status' => 'linked',
                    'finance_charge_id' => $chargeId,
                ];

                continue;
            }

            $detail = DB::transaction(function () use ($request, $chargeId): array {
                $locked = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);

                if ($locked->finance_charge_id !== null) {
                    return [
                        'dng_payment_request_id' => $locked->id,
                        'student_id' => $locked->student_id,
                        'status' => 'skipped',
                        'reason' => 'already_linked',
                    ];
                }

                $locked->update(['finance_charge_id' => $chargeId]);

                $paidSynced = $this->syncRegistrationPaidState((int) $locked->student_id, $chargeId);

                return [
                    'dng_payment_request_id' => $locked->id,
                    'student_id' => $locked->student_id,
                    'status' => 'linked',
                    'finance_charge_id' => $chargeId,
                    'paid_synced' => $paidSynced,
                ];
            });

            if (($detail['status'] ?? null) === 'linked') {
                $result['linked']++;
                if (($detail['paid_synced'] ?? false) === true) {
                    $result['paid_synced']++;
                }
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
    private function unlinkedPaidHlRequests(): Collection
    {
        return DngPaymentRequest::query()
            ->where('fee_type', self::HL)
            ->whereIn('status', self::PAID_STATUSES)
            ->whereNull('finance_charge_id')
            ->orderBy('id')
            ->get();
    }

    private function resolveRetakeChargeId(int $studentId): ?int
    {
        $registrationChargeId = CourseRetakeRegistration::query()
            ->where('student_id', $studentId)
            ->whereNotNull('finance_charge_id')
            ->orderByDesc('id')
            ->value('finance_charge_id');

        if ($registrationChargeId !== null) {
            return (int) $registrationChargeId;
        }

        $sourceChargeId = FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('source_type', CourseRetakeRegistration::class)
            ->orderByDesc('id')
            ->value('id');

        if ($sourceChargeId !== null) {
            return (int) $sourceChargeId;
        }

        $fallbackChargeId = FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->value('id');

        return $fallbackChargeId !== null ? (int) $fallbackChargeId : null;
    }

    private function syncRegistrationPaidState(int $studentId, int $chargeId): bool
    {
        $registration = CourseRetakeRegistration::query()
            ->where('student_id', $studentId)
            ->where('finance_charge_id', $chargeId)
            ->where('status', CourseRetakeRegistration::STATUS_PAYMENT_PENDING)
            ->first();

        if ($registration === null) {
            return false;
        }

        $hasPaidHl = DngPaymentRequest::query()
            ->where('finance_charge_id', $chargeId)
            ->where('fee_type', self::HL)
            ->whereIn('status', self::PAID_STATUSES)
            ->exists();

        if (! $hasPaidHl) {
            return false;
        }

        $registration->transitionToPaid(now());

        return true;
    }
}