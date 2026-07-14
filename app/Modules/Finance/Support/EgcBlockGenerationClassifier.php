<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\EgcBlock;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Services\DeferChargeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EgcBlockGenerationClassifier
{
    public const ReasonDeferredNonBillable = 'deferred_non_billable';

    public const ReasonInconsistentBlockShape = 'inconsistent_block_shape';

    public const ReasonLiveDngReviewRequired = 'live_dng_review_required';

    public const ReasonPaidDngOrPaymentReviewRequired = 'paid_dng_or_payment_review_required';

    public const ReasonAlreadyGenerated = 'already_generated';

    public const ReasonMissingCharge = 'egc_block_missing_charge';

    public const ReasonVoidedCharge = 'egc_block_charge_voided';

    public const ReasonNonCollectibleCharge = 'egc_block_charge_non_collectible';

    public const ReasonReissueExistingBlocks = 'egc_block_reissue_existing_blocks';

    private const LIVE_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PENDING,
        DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ];

    private const PAID_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    public function __construct(
        private readonly DeferChargeResolver $deferChargeResolver,
        private readonly EgcBlockFinanceResolver $blockFinanceResolver,
    ) {}

    public function classify(Student $student, int $semesterId): EgcBlockGenerationState
    {
        $blocks = $this->loadBlocks($student->id, $semesterId);

        if ($this->deferChargeResolver->isSemesterEnrollmentDeferred($student, $semesterId)) {
            return $this->blocked(self::ReasonDeferredNonBillable, $blocks);
        }

        if ($blocks->isEmpty()) {
            return new EgcBlockGenerationState(
                status: EgcBlockGenerationState::NormalGeneration,
                reason: null,
                blocks: $blocks,
                reissueBlocks: collect(),
                collectibleBlockCount: 0,
            );
        }

        if ($this->hasInconsistentShape($blocks)) {
            return $this->blocked(self::ReasonInconsistentBlockShape, $blocks);
        }

        $chargesByBlock = $this->blockFinanceResolver->chargesFor($blocks);
        $chargeIds = $chargesByBlock
            ->pluck('id')
            ->values()
            ->all();

        if ($chargeIds !== [] && $this->linkedDngRequests($chargeIds)->whereIn('status', self::LIVE_DNG_STATUSES)->exists()) {
            return $this->blocked(self::ReasonLiveDngReviewRequired, $blocks);
        }

        if ($chargeIds !== []
            && ($this->hasPaidDngEvidence($chargeIds) || $this->hasPositivePaymentEvidence($chargeIds))) {
            return $this->blocked(self::ReasonPaidDngOrPaymentReviewRequired, $blocks);
        }

        $collectibleBlocks = $blocks
            ->filter(fn (EgcBlock $block): bool => $this->isCollectible($chargesByBlock->get($block->id)))
            ->values();

        if ($collectibleBlocks->count() === $blocks->count()) {
            return new EgcBlockGenerationState(
                status: EgcBlockGenerationState::AlreadyGenerated,
                reason: self::ReasonAlreadyGenerated,
                blocks: $blocks,
                reissueBlocks: collect(),
                collectibleBlockCount: $collectibleBlocks->count(),
            );
        }

        $reissueBlocks = $blocks
            ->reject(fn (EgcBlock $block): bool => $this->isCollectible($chargesByBlock->get($block->id)))
            ->values();

        return new EgcBlockGenerationState(
            status: EgcBlockGenerationState::ReissueCandidate,
            reason: $this->reissueReason($reissueBlocks, $chargesByBlock),
            blocks: $blocks,
            reissueBlocks: $reissueBlocks,
            collectibleBlockCount: $collectibleBlocks->count(),
        );
    }

    /**
     * @return Collection<int, EgcBlock>
     */
    private function loadBlocks(int $studentId, int $semesterId): Collection
    {
        return EgcBlock::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->orderBy('block_number')
            ->get();
    }

    /**
     * @param  Collection<int, EgcBlock>  $blocks
     */
    private function hasInconsistentShape(Collection $blocks): bool
    {
        $blockNumbers = $blocks->pluck('block_number')
            ->map(fn (mixed $number): int => (int) $number)
            ->sort()
            ->values();
        $expectedBlockNumbers = range(1, $blocks->count());

        return $blocks->count() > 2
            || $blockNumbers->unique()->count() !== $blockNumbers->count()
            || $blockNumbers->all() !== $expectedBlockNumbers;
    }

    private function isCollectible(?FinanceCharge $charge): bool
    {
        if (! $charge || $charge->status !== FinanceCharge::STATUS_ACTIVE) {
            return false;
        }

        return $charge->invoiceLines()
            ->with(['invoice', 'paymentApplications'])
            ->get()
            ->contains(
                fn (InvoiceLine $line): bool => ($line->status ?? 'active') === 'active'
                    && ! in_array($line->invoice?->status, ['cancelled', 'void'], true)
            );
    }

    /**
     * @param  int[]  $chargeIds
     * @return Builder<DngPaymentRequest>
     */
    private function linkedDngRequests(array $chargeIds): Builder
    {
        $pivotRequestIds = DngPaymentRequestCharge::query()
            ->select('dng_payment_request_id')
            ->whereIn('finance_charge_id', $chargeIds);

        $installmentRequestIds = FinanceChargeInstallment::query()
            ->select('dng_payment_request_id')
            ->whereIn('finance_charge_id', $chargeIds)
            ->whereNotNull('dng_payment_request_id');

        return DngPaymentRequest::query()
            ->where(function (Builder $query) use ($pivotRequestIds, $installmentRequestIds): void {
                $query
                    ->whereIn('id', $pivotRequestIds)
                    ->orWhereIn('id', $installmentRequestIds);
            });
    }

    /**
     * @param  int[]  $chargeIds
     */
    private function hasPaidDngEvidence(array $chargeIds): bool
    {
        return $this->linkedDngRequests($chargeIds)
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('status', self::PAID_DNG_STATUSES)
                    ->orWhereNotNull('payment_id');
            })
            ->exists();
    }

    /**
     * @param  int[]  $chargeIds
     */
    private function hasPositivePaymentEvidence(array $chargeIds): bool
    {
        return PaymentApplication::query()
            ->where('amount', '>', 0)
            ->whereIn(
                'invoice_line_id',
                InvoiceLine::query()
                    ->select('id')
                    ->whereIn('charge_id', $chargeIds)
            )
            ->exists();
    }

    /**
     * @param  Collection<int, EgcBlock>  $blocks
     */
    private function reissueReason(Collection $blocks, Collection $chargesByBlock): string
    {
        if ($blocks->contains(fn (EgcBlock $block): bool => $chargesByBlock->get($block->id) === null)) {
            return self::ReasonMissingCharge;
        }

        if ($blocks->contains(fn (EgcBlock $block): bool => $chargesByBlock->get($block->id)?->status === FinanceCharge::STATUS_VOID)) {
            return self::ReasonVoidedCharge;
        }

        return self::ReasonNonCollectibleCharge;
    }

    /**
     * @param  Collection<int, EgcBlock>  $blocks
     */
    private function blocked(string $reason, Collection $blocks): EgcBlockGenerationState
    {
        return new EgcBlockGenerationState(
            status: EgcBlockGenerationState::Blocked,
            reason: $reason,
            blocks: $blocks,
            reissueBlocks: collect(),
            collectibleBlockCount: 0,
        );
    }
}
