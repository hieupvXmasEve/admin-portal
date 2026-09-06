<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected GetStudentBalanceQuery $getStudentBalanceQuery,
        protected DomainEventPublisher $domainEventPublisher,
        protected SettlementService $settlementService,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementMutationGuard $settlementMutationGuard,
        private readonly StudentReferenceReader $studentReferences,
    ) {}

    /**
     * Record a new payment.
     */
    public function recordPayment(array $data): Payment
    {
        $billingAccountId = (int) $this->billingAccountProvisioner
            ->forStudent((int) $data['student_id'])
            ->id;

        return $this->settlementMutationGuard->handle($billingAccountId, function () use ($data): Payment {
            $payment = Payment::create([
                'student_id' => $data['student_id'],
                'amount' => $data['amount'],
                'method' => $data['method'] ?? Payment::METHOD_OTHER,
                'source' => $data['source'] ?? null,
                'external_ref' => $data['external_ref'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'status' => $data['status'] ?? Payment::STATUS_COMPLETED,
                'received_by_user_id' => $data['received_by_user_id'] ?? $this->currentUserId(),
                'raw_payload' => $data['raw_payload'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->publishInvoicePaidDomainEvent($payment);

            return $payment;
        });
    }

    protected function publishInvoicePaidDomainEvent(Payment $payment): void
    {
        if (! (bool) config('notification.v2_enabled', false)) {
            return;
        }

        $writeMode = (string) config('notification.write_mode', 'off');
        if (! in_array($writeMode, ['dual', 'v2_only'], true)) {
            return;
        }

        $student = $this->studentReferences->find((int) $payment->student_id);
        if (! $student) {
            return;
        }

        $event = new DomainEvent(
            name: 'finance.invoice_paid',
            deduplicationKey: 'finance.invoice_paid:'.$payment->id,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'payment',
            aggregateId: (string) $payment->id,
            campusId: $student->campusId,
            actorUserId: $this->currentUserId(),
            payload: [
                'type_key' => 'invoice_paid',
                'student_id' => $student->id,
                'channels' => ['email', 'realtime'],
                'recipient_targets' => [
                    ['type' => 'student', 'id' => $student->id],
                ],
                'data' => [
                    'title' => 'Payment received',
                    'body' => 'Your payment has been recorded successfully.',
                    'payment_id' => (int) $payment->id,
                    'amount' => (float) $payment->amount,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'method' => (string) $payment->method,
                ],
            ],
        );

        $this->domainEventPublisher->publishAfterCommit($event);
    }

    /**
     * Allocate a payment to specific charges.
     *
     * @param  array  $allocations  Array of ['charge_id' => amount]
     * @return Collection<PaymentApplication>
     */
    public function allocatePayment(
        int $paymentId,
        array $allocations,
        ?int $userId = null,
        bool $allowHeldTargets = false,
    ): Collection {
        $payment = Payment::findOrFail($paymentId);
        $createdAllocations = collect();
        $billingAccountId = (int) $this->billingAccountProvisioner
            ->forStudent((int) $payment->student_id)
            ->id;

        $this->settlementMutationGuard->handle($billingAccountId, function () use ($payment, $allocations, $userId, $allowHeldTargets, &$createdAllocations): void {
            DB::transaction(function () use ($payment, $allocations, $userId, $allowHeldTargets, &$createdAllocations): void {
                // FIN-11/DB-09: lock the payment row and recompute the unapplied
                // amount inside the transaction. Concurrent allocators (DNG webhook
                // bridge + batch auto-allocate) serialize on this lock, so the same
                // payment can never be applied beyond its unapplied balance.
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                $remainingUnapplied = $this->settlementService->getPaymentUnappliedAmount($lockedPayment);

                foreach ($allocations as $chargeId => $amount) {
                    if ($amount <= 0 || $remainingUnapplied <= 0) {
                        continue;
                    }

                    // FIN-11/DB-09: lock the target line too. Concurrent allocators
                    // racing onto the same line serialize here and re-read its
                    // outstanding, so a line can never be applied beyond its balance.
                    $line = InvoiceLine::query()
                        ->where('charge_id', (int) $chargeId)
                        ->where('status', 'active')
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->first();

                    if (! $line) {
                        continue;
                    }

                    if (! $allowHeldTargets && DngPaymentRequestReservationTarget::query()
                        ->where('invoice_line_id', $line->id)
                        ->whereHas('dngPaymentRequest', fn ($query) => $query->holdingCollection())
                        ->lockForUpdate()
                        ->exists()) {
                        continue;
                    }

                    // Never apply one student's payment onto another student's line.
                    $lineStudentId = $line->invoice?->student_id;
                    if ($lineStudentId !== null && (int) $lineStudentId !== (int) $lockedPayment->student_id) {
                        continue;
                    }

                    // Cap by BOTH the payment's remaining unapplied amount AND the
                    // line's outstanding balance — a too-large requested amount
                    // (e.g. DNG pivot rounding) becomes unapplied credit, never line overpay.
                    $applyAmount = min(
                        (float) $amount,
                        $remainingUnapplied,
                        $this->settlementService->getLineOutstandingAmount($line),
                    );

                    if ($applyAmount <= 0) {
                        continue;
                    }

                    $allocation = $this->settlementService->createPaymentApplication(
                        $lockedPayment,
                        $line,
                        $applyAmount,
                        'application',
                        $userId ?? $this->currentUserId(),
                        self::class,
                        null,
                    );

                    $createdAllocations->push($allocation);
                    $remainingUnapplied -= $applyAmount;
                }
            });
        });

        return $createdAllocations;
    }

    /**
     * Auto-allocate a payment to outstanding invoice lines using oldest first strategy.
     *
     * FIN-01: allocation amounts come from the canonical line-level outstanding
     * calculation (invoice line truth), not the charge-centric FinanceCharge::balance
     * fallback. The two diverge once an invoice line snapshot is frozen apart from
     * the live charge amount, so the ledger-backed line outstanding is authoritative.
     */
    public function autoAllocatePayment(int $paymentId, string $strategy = 'oldest_first'): Collection
    {
        $payment = Payment::query()->findOrFail($paymentId);
        $unappliedAmount = $payment->unapplied_amount;

        if ($unappliedAmount <= 0) {
            return collect();
        }

        $lines = $this->settlementService->getOutstandingLinesForStudent(
            $payment->student_id,
            ObligationTypeRegistry::allocationPriorityOrder(),
        );

        if ($strategy !== 'oldest_first') {
            $lines = $lines->reverse()->values();
        }

        $heldLineIds = DngPaymentRequestReservationTarget::query()
            ->whereIn('invoice_line_id', $lines->pluck('id'))
            ->whereHas('dngPaymentRequest', fn ($query) => $query->holdingCollection())
            ->pluck('invoice_line_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $allocations = [];

        foreach ($lines as $line) {
            if ($unappliedAmount <= 0) {
                break;
            }

            if (in_array((int) $line->id, $heldLineIds, true)) {
                continue;
            }

            $outstanding = $this->settlementService->getLineOutstandingAmount($line);
            if ($outstanding <= 0) {
                continue;
            }

            $amountToAllocate = min($unappliedAmount, $outstanding);

            // INV-3: one active invoice line per active charge, so keying by
            // charge_id is unambiguous and matches allocatePayment()'s lookup.
            $allocations[$line->charge_id] = ($allocations[$line->charge_id] ?? 0) + $amountToAllocate;
            $unappliedAmount -= $amountToAllocate;
        }

        return $this->allocatePayment($paymentId, $allocations);
    }

    /**
     * Get outstanding charges for a student (charges with remaining balance).
     */
    public function getOutstandingCharges(int $studentId, ?int $semesterId = null): Collection
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return $query->get()->filter(function ($charge) {
            return $charge->balance > 0;
        });
    }

    /**
     * Get student balance summary.
     */
    public function getStudentBalance(int $studentId, ?int $semesterId = null): array
    {
        return $this->getStudentBalanceQuery->handle($studentId, $semesterId);
    }

    /**
     * Get unapplied credits (payment amounts not yet allocated).
     */
    public function getUnappliedCredits(int $studentId): float
    {
        $payments = Payment::where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->get();

        return $payments->sum(function ($payment) {
            return max(0, $payment->unapplied_amount);
        });
    }

    /**
     * Determine balance status.
     */
    protected function determineBalanceStatus(float $balance): string
    {
        if ($balance === 0.0) {
            return 'paid';
        } elseif ($balance > 0) {
            return 'outstanding';
        } else {
            return 'overpaid';
        }
    }

    /**
     * Get payment history for a student.
     */
    public function getPaymentHistory(int $studentId): Collection
    {
        return Payment::where('student_id', $studentId)
            ->with(['applications.invoiceLine.charge', 'receivedBy'])
            ->orderBy('paid_at', 'desc')
            ->get();
    }

    /**
     * Check if a student has fully paid for a semester.
     */
    public function isFullyPaid(int $studentId, int $semesterId): bool
    {
        $balance = $this->getStudentBalance($studentId, $semesterId);

        return $balance['balance'] <= 0;
    }

    protected function currentUserId(): ?int
    {
        $actor = Auth::user();
        if (! $actor) {
            return null;
        }

        return (int) $actor->getAuthIdentifier();
    }
}
