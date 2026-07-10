<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\DeferCase;
use App\Modules\Finance\Actions\AllocatePaymentAction;
use App\Modules\Finance\Actions\CreateStaffDebitAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
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
 *  2. Auto-safe gate (read-only, before any mutation):
 *       - live DNG on any obligation        → skipped: live_dng
 *       - discount/scholarship on any line   → skipped: discount_present
 *       - no obligations                     → noop: no_charge
 *       - scope ≠ FULL or policy unsupported → skipped: out_of_scope
 *  3. Apply inside a single transaction so a partial failure rolls back whole.
 *
 * This action is built in isolation (M1): it is NOT wired into the runtime
 * defer flow and does not change charge generation (those are M3 / M2).
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

    public const REASON_LIVE_DNG = 'live_dng';

    public const REASON_DISCOUNT_PRESENT = 'discount_present';

    public const REASON_NO_CHARGE = 'no_charge';

    public const REASON_OUT_OF_SCOPE = 'out_of_scope';

    /** DNG request statuses that are still collectible at the provider. */
    private const LIVE_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PENDING,
        DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ];

    public function __construct(
        private readonly VoidFinanceChargeAction $voidAction,
        private readonly CreateStaffDebitAction $createStaffDebitAction,
        private readonly AllocatePaymentAction $allocateAction,
        private readonly SettlementService $settlementService,
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
     *     adjustment_charge_id: int|null
     * }
     */
    public function handle(DeferCase $case, ?int $userId = null): array
    {
        $actorId = $userId ?? auth()->id();

        // Gate 1 — eligibility. Only FULL-scope PRESERVE/FORFEIT is in scope for M1.
        if (! $this->isInScope($case)) {
            return $this->result($case, self::STATUS_SKIPPED, self::REASON_OUT_OF_SCOPE);
        }

        $obligations = $this->resolveObligations($case);

        // Gate 2 — nothing to settle.
        if ($obligations->isEmpty()) {
            return $this->result($case, self::STATUS_NOOP, self::REASON_NO_CHARGE);
        }

        // Gate 3 — live DNG (read-only). A request still collectible at the
        // provider must be cancelled through the DNG flow before any void.
        if ($this->hasLiveDng($obligations)) {
            return $this->result($case, self::STATUS_SKIPPED, self::REASON_LIVE_DNG);
        }

        // Gate 4 — discount/scholarship on an obligation line (read-only). Mixing
        // a discount into a defer settlement is ambiguous; route to review.
        if ($this->hasDiscount($obligations)) {
            return $this->result($case, self::STATUS_SKIPPED, self::REASON_DISCOUNT_PRESENT);
        }

        return DB::transaction(fn (): array => $case->fee_policy === DeferCase::POLICY_FORFEIT
            ? $this->applyForfeit($case, $obligations, $actorId)
            : $this->applyPreserve($case, $obligations, $actorId));
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
     * Excludes this settlement's own FORFEIT adjustment (intake source_ref
     * defer_forfeit:{case_id}, or legacy source_type=DeferCase rows) so a re-run
     * treats an already-settled case as a noop.
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
            ->whereNot(function ($query) use ($case, $forfeitObligationIds) {
                $query->where(function ($legacy) use ($case) {
                    $legacy->where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)
                        ->where('source_type', DeferCase::class)
                        ->where('source_id', $case->id);
                })->orWhere(function ($intake) use ($forfeitObligationIds) {
                    $intake->where('charge_type', FinanceCharge::TYPE_ADJUSTMENT)
                        ->whereIn('finance_obligation_id', $forfeitObligationIds);
                });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Whether any obligation has an installment awaiting payment that is linked
     * to a live DNG request. Mirrors VoidFinanceChargeAction's own DNG guard so
     * this read-only gate and the downstream void never disagree.
     *
     * @param  Collection<int, FinanceCharge>  $obligations
     */
    private function hasLiveDng(Collection $obligations): bool
    {
        return FinanceChargeInstallment::query()
            ->whereIn('finance_charge_id', $obligations->pluck('id'))
            ->where('status', FinanceChargeInstallment::STATUS_AWAITING_PAYMENT)
            ->whereNotNull('dng_payment_request_id')
            ->whereHas('dngPaymentRequest', fn ($query) => $query->whereIn('status', self::LIVE_DNG_STATUSES))
            ->exists();
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
    private function applyPreserve(DeferCase $case, Collection $obligations, ?int $actorId): array
    {
        ['released' => $released, 'voided_charge_ids' => $voidedIds] = $this->voidObligations($case, $obligations, $actorId);

        return $this->result($case, self::STATUS_APPLIED, self::REASON_PRESERVE_SETTLED, [
            'released' => $released,
            'consumed' => 0.0,
            'voided_charge_ids' => $voidedIds,
        ]);
    }

    /**
     * FORFEIT: void every obligation, then consume the released paid cash
     * (capped by real payment) onto one adjustment charge. Unpaid → nothing to
     * consume, no adjustment, no debt.
     *
     * @param  Collection<int, FinanceCharge>  $obligations
     */
    private function applyForfeit(DeferCase $case, Collection $obligations, ?int $actorId): array
    {
        ['released' => $released, 'voided_charge_ids' => $voidedIds, 'payment_ids' => $paymentIds]
            = $this->voidObligations($case, $obligations, $actorId);

        $consumed = round($released, 2);

        if ($consumed <= 0) {
            return $this->result($case, self::STATUS_APPLIED, self::REASON_FORFEIT_SETTLED, [
                'released' => $released,
                'consumed' => 0.0,
                'voided_charge_ids' => $voidedIds,
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
            'invoice_id' => $this->resolveAdjustmentInvoiceId($case),
        ]);

        $adjustment = FinanceCharge::query()->findOrFail($result->finance_charge_id);

        $this->allocateReleasedCash($adjustment, $paymentIds, $consumed, $actorId);

        return $this->result($case, self::STATUS_APPLIED, self::REASON_FORFEIT_SETTLED, [
            'released' => $released,
            'consumed' => $consumed,
            'voided_charge_ids' => $voidedIds,
            'adjustment_charge_id' => (int) $adjustment->id,
        ]);
    }

    /**
     * Reuse the student's existing (student, semester) invoice for the
     * adjustment charge.
     *
     * Voiding the last active line of an obligation invoice cancels it, and
     * CreateFinanceChargeAction only reuses a *draft* invoice — so without this
     * the adjustment would spawn a second invoice for the same (student,
     * semester) and trip INV-6 (duplicate invoice). The defer settlement
     * guarantees at most one such invoice (INV-6 holds pre-settlement), so the
     * cancelled invoice is simply reopened to draft and reused; its derived
     * status is recomputed once the adjustment line is attached and allocated.
     */
    private function resolveAdjustmentInvoiceId(DeferCase $case): ?int
    {
        $invoice = StudentInvoice::query()
            ->where('student_id', $case->student_id)
            ->where('semester_id', $case->semester_id)
            ->orderBy('id')
            ->first();

        if ($invoice === null) {
            return null;
        }

        if ($invoice->status !== 'draft') {
            $invoice->update(['status' => 'draft']);
        }

        return (int) $invoice->id;
    }

    /**
     * Void every obligation with auto-reallocation disabled and tally released
     * paid cash plus the payments it came from.
     *
     * @param  Collection<int, FinanceCharge>  $obligations
     * @return array{released: float, voided_charge_ids: array<int, int>, payment_ids: array<int, int>}
     */
    private function voidObligations(DeferCase $case, Collection $obligations, ?int $actorId): array
    {
        $reason = "Defer settlement: {$case->fee_policy} (case #{$case->id})";
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
        ], $overrides);
    }
}
