<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Dng\Support\DngReservationTargetFingerprint;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Support\Facades\DB;

/** Coordinates the durable DNG reservation lifecycle around an external provider call. */
final class DngReservationLifecycle
{
    private const PROVIDER_RAIL = 'dng';

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly DngCampusCodeResolver $campusCodeResolver,
        private readonly DngPaymentService $dngPaymentService,
    ) {}

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details */
    public function reserveAndPush(int $studentId, string $feeType, array $details, ?array $invoiceLineIds = null, array $targetAmounts = [], array $installmentIdsByLine = []): DngPaymentRequest
    {
        return $this->push($studentId, $this->reserve($studentId, $feeType, $details, $invoiceLineIds, $targetAmounts, $installmentIdsByLine), $details);
    }

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details */
    public function reserve(int $studentId, string $feeType, array $details, ?array $requestedLineIds = null, array $targetAmounts = [], array $installmentIdsByLine = []): DngPaymentRequest
    {
        $chargeTypes = ListDngWorklistQuery::mapFeeTypeToChargeTypes($feeType);
        if ($chargeTypes === []) {
            throw new \InvalidArgumentException("DNG fee type {$feeType} has no supported Finance charge types.");
        }

        return DB::transaction(function () use ($studentId, $feeType, $chargeTypes, $details, $requestedLineIds, $targetAmounts, $installmentIdsByLine): DngPaymentRequest {
            $student = Student::query()->lockForUpdate()->findOrFail($studentId);
            $billingAccount = BillingAccount::query()->where('student_id', $student->id)->lockForUpdate()->firstOrFail();
            $campusCode = $this->campusCodeResolver->requireForStudent($student);
            $slotKey = $this->slotKey((int) $billingAccount->id, $campusCode, $feeType);
            $existing = DngPaymentRequest::query()
                ->holdingCollection()
                ->where('billing_account_id', $billingAccount->id)
                ->where('provider_rail', self::PROVIDER_RAIL)
                ->where('fee_type', $feeType)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if ($existing->campus_code !== $campusCode) {
                    throw new \RuntimeException('An unresolved DNG request exists on another campus and must be reconciled before creating a replacement.');
                }

                return $existing;
            }

            $targetLineIds = InvoiceLine::query()
                ->where('status', 'active')
                ->whereHas('charge', function ($query) use ($studentId, $chargeTypes, $details): void {
                    $query->where('student_id', $studentId)
                        ->whereIn('charge_type', $chargeTypes)
                        ->where('semester_id', $details['semester_id'])
                        ->where('status', FinanceCharge::STATUS_ACTIVE);
                })
                ->when($requestedLineIds !== null, fn ($query) => $query->whereIn('id', $requestedLineIds))
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            if ($targetLineIds === []) {
                throw new \RuntimeException('Settlement Position has no supported payable lines for this DNG fee type.');
            }

            $position = $this->settlementPositionReader->forPayableLines($targetLineIds);
            if (! $position->isValid() || $position->amounts === null || ! $position->amounts->remaining->isPositive()) {
                throw new \RuntimeException('Settlement Position is invalid, held, or has no collectible amount.');
            }

            $targets = $this->collectibleTargets($position, $targetAmounts);
            if ($targets === []) {
                throw new \RuntimeException('Settlement Position has no exact collectible targets.');
            }

            $targetLineIds = array_column($targets, 'invoice_line_id');
            InvoiceLine::query()->whereIn('id', $targetLineIds)->lockForUpdate()->get();
            $position = $this->settlementPositionReader->forPayableLines($targetLineIds);
            if (! $position->isValid() || $position->amounts === null || ! $position->amounts->remaining->isPositive()) {
                throw new \RuntimeException('Settlement Position changed while reserving DNG targets.');
            }
            $targets = $this->collectibleTargets($position, $targetAmounts);
            $targetFingerprint = $this->fingerprint($targets);
            $sequence = DngPaymentRequest::query()
                ->where('billing_account_id', $billingAccount->id)
                ->where('provider_rail', self::PROVIDER_RAIL)
                ->where('campus_code', $campusCode)
                ->where('fee_type', $feeType)
                ->lockForUpdate()
                ->count() + 1;
            $amount = array_reduce($targets, fn (Money $total, array $target): Money => $total->add(Money::vnd($target['collectible'])), Money::zero());
            $reservation = DngPaymentRequest::query()->create([
                'student_id' => $student->id,
                'billing_account_id' => $billingAccount->id,
                'campus_code' => $campusCode,
                'provider_rail' => self::PROVIDER_RAIL,
                'student_code' => $student->student_id,
                'fee_type' => $feeType,
                'description' => $details['description'],
                'semester_id' => $details['semester_id'],
                'due_date' => $details['due_date'],
                'item_id' => $this->itemId((int) $billingAccount->id, $campusCode, $feeType, $sequence),
                'active_slot_key' => $slotKey,
                'amount' => $amount->amount,
                'status' => DngPaymentRequest::STATUS_PENDING,
                'captured_settlement_version' => (int) $billingAccount->settlement_version,
                'target_fingerprint' => $targetFingerprint,
                'reserved_at' => now(),
            ]);
            foreach ($targets as $target) {
                DngPaymentRequestReservationTarget::query()->create([
                    'dng_payment_request_id' => $reservation->id,
                    'invoice_line_id' => $target['invoice_line_id'],
                    'captured_collectible' => $target['collectible'],
                    'target_identity' => $target['identity'],
                    'finance_charge_installment_id' => $installmentIdsByLine[$target['invoice_line_id']] ?? null,
                ]);
                DngPaymentRequestCharge::query()->create([
                    'dng_payment_request_id' => $reservation->id,
                    'finance_charge_id' => $target['finance_charge_id'],
                    'amount' => $target['collectible'],
                    'finance_charge_installment_id' => $installmentIdsByLine[$target['invoice_line_id']] ?? null,
                ]);
            }
            $billingAccount->increment('settlement_version');

            return $reservation;
        });
    }

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details */
    public function push(int $studentId, DngPaymentRequest $reservation, array $details): DngPaymentRequest
    {
        if ($reservation->status === DngPaymentRequest::STATUS_UNKNOWN_OUTCOME) {
            throw new \RuntimeException("DNG reservation #{$reservation->id} has an unknown provider outcome and must be reconciled before retry.");
        }
        if ($reservation->status === DngPaymentRequest::STATUS_NEEDS_REVIEW) {
            throw new \RuntimeException("DNG reservation #{$reservation->id} has changed targets and requires Finance review.");
        }
        if ($reservation->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG) {
            return $reservation;
        }

        $student = Student::query()->findOrFail($studentId);
        $payload = $this->providerPayload($student, $reservation, $details);
        try {
            $response = $this->dngPaymentService->pushReserved($payload);
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($reservation, $exception): void {
                DngPaymentRequest::query()->lockForUpdate()->findOrFail($reservation->id)->update([
                    'status' => DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
                    'error_message' => $exception->getMessage(),
                ]);
            });

            throw $exception;
        }

        return $this->finalize($reservation->id, $response, $payload);
    }

    /** @param array{Code: int, Type: string, Message: string, data: mixed} $response @param array<string, mixed> $payload */
    private function finalize(int $reservationId, array $response, array $payload): DngPaymentRequest
    {
        return DB::transaction(function () use ($reservationId, $response, $payload): DngPaymentRequest {
            $reservation = DngPaymentRequest::query()->lockForUpdate()->findOrFail($reservationId);
            BillingAccount::query()->lockForUpdate()->findOrFail($reservation->billing_account_id);
            $targetIds = $reservation->reservationTargets()->pluck('invoice_line_id')->map(fn ($id): int => (int) $id)->all();
            InvoiceLine::query()->whereIn('id', $targetIds)->lockForUpdate()->get();
            $position = $this->settlementPositionReader->forPayableLines($targetIds);
            $currentTargets = $position->isValid() ? $this->collectibleTargets($position) : [];
            $currentByLine = collect($currentTargets)->keyBy('invoice_line_id');
            $targets = $reservation->reservationTargets()->orderBy('invoice_line_id')->get()
                ->map(function (DngPaymentRequestReservationTarget $target) use ($currentByLine): ?array {
                    $current = $currentByLine->get((int) $target->invoice_line_id);
                    if ($current === null || Money::vnd((string) $target->captured_collectible)->isGreaterThan(Money::vnd($current['collectible']))) {
                        return null;
                    }

                    return [
                        'invoice_line_id' => (int) $target->invoice_line_id,
                        'finance_charge_id' => (int) $current['finance_charge_id'],
                        'collectible' => (string) $target->captured_collectible,
                        'identity' => (string) $target->target_identity,
                    ];
                })->filter()->values()->all();
            if ($targets === [] || $this->fingerprint($targets) !== $reservation->target_fingerprint) {
                $reservation->update([
                    'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW,
                    'push_payload' => $payload,
                    'push_response' => $response,
                    'error_message' => 'Reserved Settlement Position targets changed before DNG finalization.',
                ]);

                return $reservation->fresh();
            }
            $reservation->update([
                'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                'push_payload' => $payload,
                'push_response' => $response,
                'dng_transaction_id' => $response['data']['TransactionID'] ?? $response['data']['Id'] ?? null,
                'dng_payment_id' => $response['data']['PaymentId'] ?? $response['data']['OtherId'] ?? null,
            ]);

            return $reservation->fresh();
        });
    }

    /** @return list<array{invoice_line_id: int, finance_charge_id: int, collectible: string, identity: string}> */
    private function collectibleTargets(SettlementPosition $position, array $targetAmounts = []): array
    {
        $lineIds = collect($position->payable_line_breakdown)
            ->filter(fn (SettlementPosition $line): bool => $line->amounts !== null && $line->amounts->remaining->isPositive())
            ->map(fn (SettlementPosition $line): int => (int) $line->payable_line_id)->all();
        $lines = InvoiceLine::query()->whereIn('id', $lineIds)->get()->keyBy('id');

        return collect($position->payable_line_breakdown)
            ->filter(fn (SettlementPosition $line): bool => $line->amounts !== null && $line->amounts->remaining->isPositive())
            ->sortBy('payable_line_id')
            ->map(function (SettlementPosition $line) use ($lines, $targetAmounts): array {
                $invoiceLineId = (int) $line->payable_line_id;
                $invoiceLine = $lines->get($invoiceLineId);
                if ($invoiceLine === null || $invoiceLine->charge_id === null) {
                    throw new \RuntimeException("Payable line #{$invoiceLineId} cannot be reserved without a FinanceCharge.");
                }
                $collectible = $line->amounts->remaining;
                if (isset($targetAmounts[$invoiceLineId])) {
                    $requested = Money::vnd((string) $targetAmounts[$invoiceLineId]);
                    if (! $requested->isPositive()) {
                        throw new \RuntimeException("DNG target for payable line #{$invoiceLineId} must be positive.");
                    }
                    if ($requested->isGreaterThan($collectible)) {
                        throw new \RuntimeException("Payable line #{$invoiceLineId} no longer covers its installment target.");
                    }
                    if ($collectible->isGreaterThan($requested)) {
                        $collectible = $requested;
                    }
                }

                return [
                    'invoice_line_id' => $invoiceLineId,
                    'finance_charge_id' => (int) $invoiceLine->charge_id,
                    'collectible' => $collectible->amount,
                    'identity' => "invoice_line:{$invoiceLineId}",
                ];
            })->values()->all();
    }

    /** @param list<array{invoice_line_id: int, finance_charge_id: int, collectible: string, identity: string}> $targets */
    private function fingerprint(array $targets): string
    {
        return DngReservationTargetFingerprint::make($targets);
    }

    private function slotKey(int $billingAccountId, string $campusCode, string $feeType): string
    {
        return implode(':', [self::PROVIDER_RAIL, $campusCode, $billingAccountId, $feeType]);
    }

    private function itemId(int $billingAccountId, string $campusCode, string $feeType, int $sequence): string
    {
        return 'swinx-rsv-'.substr(hash('sha256', implode('|', [self::PROVIDER_RAIL, $campusCode, $billingAccountId, $feeType, $sequence])), 0, 32);
    }

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details @return array<string, mixed> */
    private function providerPayload(Student $student, DngPaymentRequest $reservation, array $details): array
    {
        return [
            'campus_code' => $reservation->campus_code,
            'student_code' => $student->student_id,
            'fee_type' => $reservation->fee_type,
            'type' => $reservation->fee_type,
            'description' => $reservation->description,
            'semester_id' => $details['semester_id'],
            'due_date' => $details['due_date'],
            'item_id' => $reservation->item_id,
            'amount' => $reservation->amount,
            'student_name' => $student->full_name,
            'email' => $student->email ?? '',
            'estimate_time' => $details['estimate_time'],
            'student_address' => $student->current_address_line ?? $student->address ?? '',
            'cccd' => $student->national_id ?? null,
        ];
    }
}
