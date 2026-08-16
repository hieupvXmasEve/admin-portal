<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Actions\AllocatePaymentAction;
use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Actions\CreateStaffDebitAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\DeferCase;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FIN-REV-020-01 (M1) — Defer Policy Settlement Action.
 *
 * Settles a FULL-scope PRESERVE/FORFEIT defer case using only existing ledger
 * operations — no new ledger, no new table. The defer policy applies to real
 * paid cash only:
 *
 *  - PRESERVE: void the deferred obligations; released paid cash stays
 *    unapplied/available for the student. Nothing else.
 *  - FORFEIT: void the deferred obligations, then consume the released paid
 *    cash (capped by what was actually paid) onto a single adjustment charge.
 *    If the student paid nothing there is nothing to consume — never create
 *    unpaid forfeit debt.
 *
 * Settlement model ("void-and-release → re-consume"):
 *  1. Resolve obligations = active positive finance_charges for the student in
 *     the defer semester.
 *  2. Auto-safe gate and lifecycle collection closure:
 *       - live unpaid DNG on an obligation  → cancel locally in Swinx
 *       - discount/scholarship on any line   → skipped: discount_present
 *       - no obligations                     → noop: no_charge
 *       - scope ≠ FULL or policy unsupported → skipped: out_of_scope
 *  3. Apply inside a single transaction so a partial failure rolls back whole.
 *
 * The runtime Academic defer flow and historical defer backfill both reuse this
 * action; it does not change charge generation.
 * Traceability: FORFEIT adjustment via intake source_ref defer_forfeit:{case_id};
 * void_reason on released obligations. Wave 7 no longer writes charge source_type.
 */
class ApplyDeferFinancePolicyAction
{
    // Status (deterministic).
    public const STATUS_APPLIED = 'applied';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_NOOP = 'noop';

    // Reasons.
    public const REASON_PRESERVE_SETTLED = 'preserve_settled';

    public const REASON_FORFEIT_SETTLED = 'forfeit_settled';

    public const REASON_DISCOUNT_PRESENT = 'discount_present';

    public const REASON_NO_CHARGE = 'no_charge';

    public const REASON_OUT_OF_SCOPE = 'out_of_scope';

    public const REASON_STUDENT_LIFECYCLE_ACTIVE = 'student_lifecycle_active';

    private const SETTLEABLE_LIFECYCLE_STATUSES = [
        'deferred',
        'dropout',
        'dropout_transfer',
        'pending_course_opening',
    ];

    /** DNG request statuses that are still collectible at the provider. */
    private const LIVE_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PENDING,
        DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
        DngPaymentRequest::STATUS_NEEDS_REVIEW,
    ];

    public function __construct(
        private readonly CancelDngPaymentRequestAction $cancelDngPaymentRequestAction,
        private readonly VoidFinanceChargeAction $voidAction,
        private readonly CreateStaffDebitAction $createStaffDebitAction,
        private readonly AllocatePaymentAction $allocateAction,
        private readonly InvoiceGenerationService $invoiceGenerationService,
        private readonly SettlementService $settlementService,
        private readonly StudentLifecycleStatusReader $studentLifecycleStatusReader,
    ) {}

    /**
     * Settle a FULL-scope PRESERVE/FORFEIT defer case.
     *
     * @return array{
     *     status: string,
     *     reason: string,
     *     policy: string,
     *     released: float,
     *     consumed: float,
     *     voided_charge_ids: array<int, int>,
     *     cancelled_dng_request_ids: array<int, int>,
     *     adjustment_charge_id: int|null
     * }
     */
    public function handle(
        DeferCase $case,
        ?int $userId = null,
        ?string $reviewedDiscountDispositionReason = null,
    ): array {
        $actorId = $userId ?? auth()->id();
        $reviewedDiscountDispositionReason = trim((string) $reviewedDiscountDispositionReason);

        // Gate 1 — eligibility. Only FULL-scope PRESERVE/FORFEIT is in scope for M1.
        if (! $this->isInScope($case)) {
            return $this->result($case, self::STATUS_SKIPPED, self::REASON_OUT_OF_SCOPE);
        }

        $studentLifecycleStatus = $this->studentLifecycleStatusReader
            ->statusesFor([(int) $case->student_id])[(int) $case->student_id] ?? null;
        if (! in_array($studentLifecycleStatus, self::SETTLEABLE_LIFECYCLE_STATUSES, true)) {
            return $this->result($case, self::STATUS_SKIPPED, self::REASON_STUDENT_LIFECYCLE_ACTIVE);
        }

        return DB::transaction(function () use ($case, $actorId, $reviewedDiscountDispositionReason): array {
            $obligations = $this->resolveObligations($case);

            if ($obligations->isEmpty()) {
                return $this->result($case, self::STATUS_NOOP, self::REASON_NO_CHARGE);
            }

            // Do not close collection unless the linked charges are also safe to
            // void in this transaction. Discounted cases stay in review.
            if ($this->hasDiscount($obligations)
                && ! ($reviewedDiscountDispositionReason !== '' && $case->fee_policy === DeferCase::POLICY_FORFEIT)) {
                return $this->result($case, self::STATUS_SKIPPED, self::REASON_DISCOUNT_PRESENT);
            }

            $cancelledDngRequestIds = $this->cancelLiveDngRequests($obligations);

            return $case->fee_policy === DeferCase::POLICY_FORFEIT
                ? $this->applyForfeit(
                    $case,
                    $obligations,
                    $actorId,
                    $cancelledDngRequestIds,
                    $reviewedDiscountDispositionReason,
                )
                : $this->applyPreserve($case, $obligations, $actorId, $cancelledDngRequestIds);
        });
    }

    /** FULL-scope, PRESERVE or FORFEIT only (PARTIAL / COURSES are later slices). */
    private function isInScope(DeferCase $case): bool
    {
        return $case->scope_type === DeferCase::SCOPE_FULL
            && in_array($case->fee_policy, [DeferCase::POLICY_PRESERVE, DeferCase::POLICY_FORFEIT], true);
    }

    /**
     * Active positive finance charges for the student in the defer semester.
     *
     * Excludes this settlement's own FORFEIT adjustment by its canonical intake
     * identity so a re-run treats an already-settled case as a noop.
     *
     * @return Collection<int, FinanceCharge>
     */
    private function resolveObligations(DeferCase $case): Collection
    {
        $forfeitObligationIds = FinanceObligation::query()
            ->where('source_system', CreateStaffDebitAction::SOURCE_SYSTEM)
            ->where('source_kind', CreateStaffDebitAction::SOURCE_KIND_DEFER_FORFEIT)
            ->where('source_ref', 'defer_forfeit:'.$case->id)
            ->where('obligation_type', FinanceCharge::TYPE_ADJUSTMENT)
            ->pluck('id');

        return FinanceCharge::query()
            ->where('student_id', $case->student_id)
            ->where('semester_id', $case->semester_id)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0)
            ->where(function ($query) use ($forfeitObligationIds): void {
                $query->whereNull('finance_obligation_id')
                    ->orWhereNotIn('finance_obligation_id', $forfeitObligationIds);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Close all live unpaid DNG requests linked through any supported exact-link
     * shape. This is a local admin lifecycle closure: no provider API is called.
     *
     * @param  Collection<int, FinanceCharge>  $obligations
     * @return list<int>
     */
    private function cancelLiveDngRequests(Collection $obligations): array
    {
        $chargeIds = $obligations->modelKeys();
        $requestIds = FinanceChargeInstallment::query()
            ->whereIn('finance_charge_id', $chargeIds)
            ->where('status', FinanceChargeInstallment::STATUS_AWAITING_PAYMENT)
            ->whereNotNull('dng_payment_request_id')
            ->whereHas('dngPaymentRequest', fn ($query) => $query->whereIn('status', self::LIVE_DNG_STATUSES))
            ->pluck('dng_payment_request_id');

        $requestIds = $requestIds->merge(DngPaymentRequestCharge::query()
            ->whereIn('finance_charge_id', $chargeIds)
            ->whereHas('dngPaymentRequest', fn ($requests) => $requests->whereIn('status', self::LIVE_DNG_STATUSES))
            ->pluck('dng_payment_request_id'));

        $lineIds = InvoiceLine::query()
            ->whereIn('charge_id', $chargeIds)
            ->pluck('id');

        $requestIds = $requestIds->merge(DngPaymentRequestReservationTarget::query()
            ->where(function ($targets) use ($chargeIds, $lineIds): void {
                $targets->whereIn('invoice_line_id', $lineIds)
                    ->orWhereHas(
                        'financeChargeInstallment',
                        fn ($installments) => $installments->whereIn('finance_charge_id', $chargeIds),
                    );
            })
            ->whereHas('dngPaymentRequest', fn ($requests) => $requests->whereIn('status', self::LIVE_DNG_STATUSES))
            ->pluck('dng_payment_request_id'))
            ->filter()
            ->map(static fn (int|string $requestId): int => (int) $requestId)
            ->unique()
            ->values();

        $requests = DngPaymentRequest::query()
            ->whereIn('id', $requestIds)
            ->whereIn('status', self::LIVE_DNG_STATUSES)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($requests as $request) {
            $this->cancelDngPaymentRequestAction->runLocallyForLifecycle($request);
        }

        return $requests->modelKeys();
    }

    /**
     * Whether any obligation line carries a (non-reversed) discount/scholarship
     * allocation.
     *
     * @param  Collection<int, FinanceCharge>  $obligations
     */
    private function hasDiscount(Collection $obligations): bool
    {
        return $obligations->contains(
            fn (FinanceCharge $charge): bool => $this->settlementService->getChargeDiscountAmount((int) $charge->id) > 0
        );
    }

    /**
     * PRESERVE: void every obligation with auto-reallocation disabled so the
     * released paid cash stays unapplied/available. No adjustment, no debt.
     *
     * @param  Collection<int, FinanceCharge>  $obligations
     */
    private function applyPreserve(
        DeferCase $case,
        Collection $obligations,
        ?int $actorId,
        array $cancelledDngRequestIds,
    ): array {
        ['released' => $released, 'voided_charge_ids' => $voidedIds] = $this->voidObligations($case, $obligations, $actorId);

        return $this->result($case, self::STATUS_APPLIED, self::REASON_PRESERVE_SETTLED, [
            'released' => $released,
            'consumed' => 0.0,
            'voided_charge_ids' => $voidedIds,
            'cancelled_dng_request_ids' => $cancelledDngRequestIds,
        ]);
    }

    /**
     * FORFEIT: void every obligation, then consume the released paid cash
     * (capped by real payment) onto one adjustment charge. Unpaid → nothing to
     * consume, no adjustment, no debt.
     *
     * @param  Collection<int, FinanceCharge>  $obligations
     */
    private function applyForfeit(
        DeferCase $case,
        Collection $obligations,
        ?int $actorId,
        array $cancelledDngRequestIds,
        string $reviewedDiscountDispositionReason,
    ): array {
        $adjustmentInvoiceContext = $this->resolveAdjustmentInvoiceContext($obligations);

        ['released' => $released, 'voided_charge_ids' => $voidedIds, 'payment_ids' => $paymentIds]
            = $this->voidObligations(
                $case,
                $obligations,
                $actorId,
                $reviewedDiscountDispositionReason,
            );

        $consumed = round($released, 2);

        if ($consumed <= 0) {
            return $this->result($case, self::STATUS_APPLIED, self::REASON_FORFEIT_SETTLED, [
                'released' => $released,
                'consumed' => 0.0,
                'voided_charge_ids' => $voidedIds,
                'cancelled_dng_request_ids' => $cancelledDngRequestIds,
            ]);
        }

        $result = $this->createStaffDebitAction->handle([
            'student_id' => $case->student_id,
            'semester_id' => $case->semester_id,
            'charge_type' => FinanceCharge::TYPE_ADJUSTMENT,
            'amount' => $consumed,
            'description' => "Defer forfeit settlement (case #{$case->id})",
            'source_kind' => CreateStaffDebitAction::SOURCE_KIND_DEFER_FORFEIT,
            'source_ref' => 'defer_forfeit:'.$case->id,
            'invoice_id' => $this->prepareAdjustmentInvoice($adjustmentInvoiceContext['invoice_id']),
        ]);

        $adjustment = FinanceCharge::query()->findOrFail($result->finance_charge_id);

        $this->allocateReleasedCash($adjustment, $paymentIds, $consumed, $actorId);
        $this->finalizeAdjustmentInvoice($adjustment, $adjustmentInvoiceContext['paid_at']);

        return $this->result($case, self::STATUS_APPLIED, self::REASON_FORFEIT_SETTLED, [
            'released' => $released,
            'consumed' => $consumed,
            'voided_charge_ids' => $voidedIds,
            'adjustment_charge_id' => (int) $adjustment->id,
            'cancelled_dng_request_ids' => $cancelledDngRequestIds,
        ]);
    }

    /**
     * Reuse the invoice that carried the voided obligation for the adjustment
     * charge, preserving one statement lineage for the defer settlement.
     *
     * Voiding the last active line of an obligation invoice cancels it, and
     * CreateFinanceChargeAction only reuses a *draft* invoice — so without this
     * the adjustment would spawn a separate statement detached from the voided
     * obligation's history. Multiple invoices per student/semester are valid;
     * this reuse is about lifecycle cohesion, not invoice cardinality. The
     * cancelled invoice is reopened to draft and its derived status is recomputed
     * once the adjustment line is attached and allocated.
     */
    private function prepareAdjustmentInvoice(?int $invoiceId): ?int
    {
        if ($invoiceId === null) {
            return null;
        }

        $invoice = StudentInvoice::query()->lockForUpdate()->find($invoiceId);

        if ($invoice === null) {
            return null;
        }

        if ($invoice->status !== 'draft') {
            $billingAccountId = (int) app(BillingAccountProvisioner::class)->forStudent((int) $invoice->student_id)->id;
            app(SettlementMutationGuard::class)->handle($billingAccountId, function () use ($invoice): void {
                $invoice->update(['status' => 'draft']);
            });
        }

        return (int) $invoice->id;
    }

    /**
     * Capture one exact source statement before its payable lines are voided.
     * Multiple invoices per semester are valid, so the adjustment must reuse an
     * invoice that actually contains one of this settlement's obligations.
     *
     * @param  Collection<int, FinanceCharge>  $obligations
     * @return array{invoice_id: int|null, paid_at: string|null}
     */
    private function resolveAdjustmentInvoiceContext(Collection $obligations): array
    {
        $invoice = StudentInvoice::query()
            ->whereHas(
                'invoiceLines',
                fn ($lines) => $lines->whereIn('charge_id', $obligations->modelKeys()),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id', 'status', 'cached_paid_at']);

        if ($invoice === null) {
            return ['invoice_id' => null, 'paid_at' => null];
        }

        return [
            'invoice_id' => (int) $invoice->id,
            'paid_at' => $invoice->status === 'paid' && $invoice->cached_paid_at !== null
                ? $invoice->cached_paid_at->format('Y-m-d H:i:s')
                : null,
        ];
    }

    private function finalizeAdjustmentInvoice(FinanceCharge $adjustment, ?string $preservedPaidAt): void
    {
        $invoice = $adjustment->invoiceLines()
            ->with('invoice')
            ->firstOrFail()
            ->invoice()
            ->firstOrFail();

        $this->invoiceGenerationService->closeInvoice($invoice);
        $this->settlementService->recalculateInvoiceSnapshot($invoice->fresh(), $preservedPaidAt);
    }

    /**
     * Void every obligation with auto-reallocation disabled and tally released
     * paid cash plus the payments it came from.
     *
     * @param  Collection<int, FinanceCharge>  $obligations
     * @return array{released: float, voided_charge_ids: array<int, int>, payment_ids: array<int, int>}
     */
    private function voidObligations(
        DeferCase $case,
        Collection $obligations,
        ?int $actorId,
        string $reviewedDiscountDispositionReason = '',
    ): array {
        $reason = "Defer settlement: {$case->fee_policy} (case #{$case->id})";
        if ($reviewedDiscountDispositionReason !== '') {
            $reason .= '; reviewed discount disposition: '.$reviewedDiscountDispositionReason;
        }
        $released = 0.0;
        $voidedIds = [];
        $paymentIds = [];

        foreach ($obligations as $obligation) {
            $void = $this->voidAction->handle((int) $obligation->id, $reason, $actorId, autoReallocate: false);

            $released += (float) $void['released_amount'];
            $voidedIds[] = (int) $obligation->id;
            $paymentIds = array_merge($paymentIds, array_map('intval', $void['affected_payments']));
        }

        return [
            'released' => round($released, 2),
            'voided_charge_ids' => $voidedIds,
            'payment_ids' => array_values(array_unique($paymentIds)),
        ];
    }

    /**
     * Allocate the consumed amount onto the adjustment charge, drawing from the
     * payments freed by the void. Capped by each payment's unapplied balance and
     * by the remaining amount so the adjustment is consumed exactly, never over.
     *
     * @param  array<int, int>  $paymentIds
     */
    private function allocateReleasedCash(FinanceCharge $adjustment, array $paymentIds, float $consumed, ?int $actorId): void
    {
        $remaining = $consumed;

        $payments = Payment::query()
            ->whereIn('id', $paymentIds)
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            if ($remaining <= 0) {
                break;
            }

            $available = $this->settlementService->getPaymentUnappliedAmount($payment);

            if ($available <= 0) {
                continue;
            }

            $allocate = round(min($available, $remaining), 2);

            if ($allocate <= 0) {
                continue;
            }

            $this->allocateAction->run($payment, $adjustment, $allocate, $actorId);
            $remaining = round($remaining - $allocate, 2);
        }
    }

    /**
     * Build the deterministic result envelope.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function result(DeferCase $case, string $status, string $reason, array $overrides = []): array
    {
        return array_merge([
            'status' => $status,
            'reason' => $reason,
            'policy' => $case->fee_policy,
            'released' => 0.0,
            'consumed' => 0.0,
            'voided_charge_ids' => [],
            'adjustment_charge_id' => null,
            'cancelled_dng_request_ids' => [],
        ], $overrides);
    }
}
