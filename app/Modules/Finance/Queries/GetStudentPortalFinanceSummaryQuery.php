<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Finance\StudentPortalFinanceSummaryReader;

/**
 * Compatibility read model for the original student finance summary routes.
 *
 * The endpoint response is intentionally distinct from the newer finance
 * presentation endpoints. Keep its field names and status mapping stable until
 * the portal contract is deliberately consolidated.
 */
final class GetStudentPortalFinanceSummaryQuery implements StudentPortalFinanceSummaryReader
{
    public function __construct(
        private readonly StudentFinanceSettlementPositionReader $positionReader,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    /** @return array<string, mixed> */
    public function handle(int $studentId): array
    {
        $globalPosition = $this->positionReader->current($studentId);
        $invoices = StudentInvoice::query()
            ->with(['invoiceLines.charge'])
            ->where('student_id', $studentId)
            ->get();

        $semesters = $invoices
            ->groupBy('semester_id')
            ->map(function ($semesterInvoices, int|string $semesterId) use ($studentId): ?array {
                $semester = $this->academicPeriods->find((int) $semesterId);
                if ($semester === null) {
                    return null;
                }

                $position = $this->positionReader->current($studentId, $semester->id);
                $valid = (bool) $position['valid'];
                $totalDue = $valid ? (float) $position['net_due'] : null;
                $totalPaid = $valid ? (float) $position['cash_applied'] : null;
                $balance = $valid ? (float) $position['remaining_collectible'] : null;

                return [
                    'semester_id' => $semester->id,
                    'semester_code' => $semester->code,
                    'semester_name' => $semester->name,
                    'start_date' => $semester->start_date,
                    'due_amount' => $totalDue,
                    'paid_amount' => $totalPaid,
                    'balance' => $balance,
                    'status' => $this->status($valid, $totalDue, $totalPaid, $balance),
                    'settlement_position' => [
                        'valid' => $valid,
                        'message' => $valid ? null : StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
                    ],
                    'badges' => $this->badgesFor($semesterInvoices),
                ];
            })
            ->filter()
            ->sortByDesc('start_date')
            ->values()
            ->all();

        return [
            'global_balance' => $globalPosition['valid'] ? $globalPosition['unapplied_cash'] : null,
            'settlement_position' => [
                'valid' => (bool) $globalPosition['valid'],
                'message' => $globalPosition['valid'] ? null : StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
            ],
            'semesters' => $semesters,
        ];
    }

    /** @return array<string, mixed>|null */
    public function semesterFor(int $studentId, int $semesterId): ?array
    {
        $semester = $this->academicPeriods->find($semesterId);
        if ($semester === null) {
            return null;
        }

        $position = $this->positionReader->current($studentId, $semesterId);
        $positionValid = (bool) $position['valid'];
        $charges = FinanceCharge::query()
            ->forStudent($studentId)
            ->forSemester($semesterId)
            ->with(['source', 'invoiceLines.paymentApplications.payment'])
            ->orderBy('effective_at')
            ->get();

        $lines = $charges->map(static function (FinanceCharge $charge): array {
            return [
                'id' => $charge->id,
                'type' => $charge->charge_type,
                'amount' => (float) $charge->amount,
                'description' => $charge->description,
                'effective_at' => $charge->effective_at,
                'voided_at' => $charge->voided_at,
                'is_void' => $charge->status === FinanceCharge::STATUS_VOID,
                'paid_amount' => $charge->paid_amount,
            ];
        });

        $payments = collect();
        foreach ($charges as $charge) {
            foreach ($charge->invoiceLines as $line) {
                foreach ($line->paymentApplications as $application) {
                    $payment = $application->payment;

                    if ($payment !== null && (float) $application->amount > 0) {
                        $payments->push([
                            'payment_id' => $payment->id,
                            'payment_date' => $payment->paid_at,
                            'method' => $payment->method,
                            'total_payment_amount' => (float) $payment->amount,
                            'allocated_to_this_semester' => (float) $application->amount,
                        ]);
                    }
                }
            }
        }

        $groupedPayments = $payments->groupBy('payment_id')->map(static function ($items): array {
            $first = $items->first();

            return [
                'id' => $first['payment_id'],
                'date' => $first['payment_date'],
                'method' => $first['method'],
                'amount' => $items->sum('allocated_to_this_semester'),
                'original_total' => $first['total_payment_amount'],
            ];
        })->values();

        return [
            'semester' => [
                'id' => $semester->id,
                'code' => $semester->code,
                'name' => $semester->name,
            ],
            'charges' => $lines,
            'payments' => $groupedPayments,
            'summary' => [
                'due_amount' => $positionValid ? $position['net_due'] : null,
                'paid_amount' => $positionValid ? $position['cash_applied'] : null,
                'credit_amount' => $positionValid ? $position['credit_applied'] : null,
                'balance' => $positionValid ? $position['remaining_collectible'] : null,
                'settlement_position' => [
                    'valid' => $positionValid,
                    'message' => $positionValid ? null : StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
                    'issues' => $position['issues'],
                ],
            ],
        ];
    }

    /** @param iterable<StudentInvoice> $invoices
     * @return list<string>
     */
    private function badgesFor(iterable $invoices): array
    {
        $chargeTypes = collect($invoices)
            ->flatMap(static fn (StudentInvoice $invoice) => $invoice->invoiceLines->pluck('charge.charge_type'))
            ->filter()
            ->unique();
        $badges = [];

        if ($chargeTypes->contains(FinanceCharge::TYPE_EGC_LEVEL_FEE)) {
            $badges[] = 'EGC';
        }
        if ($chargeTypes->contains(FinanceCharge::TYPE_RETAKE_FEE)) {
            $badges[] = 'Retake';
        }
        if ($chargeTypes->contains(FinanceEntitlementType::ScholarshipCredit)) {
            $badges[] = 'Scholarship';
        }
        if ($chargeTypes->contains(FinanceEntitlementType::VoucherCredit)) {
            $badges[] = 'Voucher';
        }
        if ($chargeTypes->contains(FinanceEntitlementType::DeferCredit)) {
            $badges[] = 'Defer';
        }
        if ($chargeTypes->contains(FinanceCharge::TYPE_TUITION_TERM)) {
            $badges[] = 'Major';
        }

        return array_values(array_unique($badges));
    }

    private function status(bool $valid, ?float $totalDue, ?float $totalPaid, ?float $balance): string
    {
        if (! $valid) {
            return 'unknown';
        }

        if ($totalDue <= 0 && $totalPaid === 0.0) {
            return 'no_fee';
        }
        if (abs((float) $balance) < 0.01) {
            return 'paid';
        }
        if ((float) $balance < 0) {
            return 'overpaid';
        }

        return $totalPaid > 0 ? 'partial' : 'unpaid';
    }
}
