<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Dng\Support\DngActiveMigrationReport;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Database\Eloquent\Collection;

final class DngActiveMigrationInventoryQuery
{
    private const PROVIDER_RAIL = 'dng';

    private const ACTIVE_STATUSES = [
        DngPaymentRequest::STATUS_PENDING,
        DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
        DngPaymentRequest::STATUS_NEEDS_REVIEW,
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    private const UNPAID_ACTIVE_STATUSES = [
        DngPaymentRequest::STATUS_PENDING,
        DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
        DngPaymentRequest::STATUS_NEEDS_REVIEW,
    ];

    private const PAID_ACTIVE_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    /** @var array<int, array{targets: list<array<string, mixed>>, classifications: list<string>, billing_account_id: ?int, campus_code: ?string, provider_rail: ?string, fee_type: string}> */
    private array $inspections = [];

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly StudentLifecycleStatusReader $studentLifecycleStatusReader,
    ) {}

    public function handle(?int $limit = null): DngActiveMigrationReport
    {
        $this->inspections = [];
        $records = [];

        $query = DngPaymentRequest::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->with(['student.campus', 'reservationTargets', 'chargeLinks', 'payment']);
        $total = (clone $query)->count();

        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->orderBy('id')->chunkById(100, function (Collection $requests) use (&$records): void {
            $studentStatuses = $this->studentLifecycleStatusReader->statusesFor(
                $requests->pluck('student_id')
                    ->map(static fn (int|string $studentId): int => (int) $studentId)
                    ->unique()
                    ->values()
                    ->all(),
            );

            foreach ($requests as $request) {
                $inspection = $this->inspect(
                    $request,
                    $studentStatuses[(int) $request->student_id] ?? null,
                );
                $this->inspections[$request->id] = $inspection;
                $records[] = [
                    'id' => (int) $request->id,
                    'status' => (string) $request->status,
                    'classifications' => $inspection['classifications'],
                    'action' => $this->actionFor($inspection['classifications']),
                    'billing_account_id' => $inspection['billing_account_id'],
                    'target_line_ids' => array_values(array_map(
                        static fn (array $target): int => (int) $target['invoice_line_id'],
                        $inspection['targets'],
                    )),
                ];
            }
        });

        $this->addConflictClassifications($records);
        $counts = $this->counts($records);
        $counts['unmatched_receipt'] = DngReceiptException::query()
            ->where('status', DngReceiptException::STATUS_OPEN)
            ->where('exception_type', 'unmatched_provider_receipt')
            ->count();

        foreach ($records as &$record) {
            $record['classifications'] = $this->inspections[$record['id']]['classifications'];
            $record['action'] = $this->actionFor($record['classifications']);
        }
        unset($record);

        return new DngActiveMigrationReport(
            total: count($records),
            counts: $counts,
            records: $records,
            backfilled: 0,
            complete: $limit === null && count($records) === $total,
        );
    }

    /** @return array{targets: list<array<string, mixed>>, classifications: list<string>, billing_account_id: ?int, campus_code: ?string, provider_rail: ?string, fee_type: string} */
    public function inspectionFor(int $requestId): array
    {
        if (! isset($this->inspections[$requestId])) {
            throw new \LogicException('Run the DNG inventory query before reading an inspection.');
        }

        return $this->inspections[$requestId];
    }

    /** @return array{targets: list<array<string, mixed>>, classifications: list<string>, billing_account_id: ?int, campus_code: ?string, provider_rail: ?string, fee_type: string} */
    private function inspect(DngPaymentRequest $request, ?string $studentLifecycleStatus): array
    {
        $classifications = [];
        $studentAccountIds = BillingAccount::query()
            ->where('student_id', $request->student_id)
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $billingAccountId = $request->billing_account_id !== null
            ? (int) $request->billing_account_id
            : (count($studentAccountIds) === 1 ? $studentAccountIds[0] : null);

        if ($billingAccountId === null || ! in_array($billingAccountId, $studentAccountIds, true)) {
            $classifications[] = 'unknown_link';
        }

        $campusCode = trim((string) $request->campus_code);
        $studentCampusCode = trim((string) $request->student?->campus?->getDngCode());
        if ($campusCode === '' || $studentCampusCode === '' || $campusCode !== $studentCampusCode) {
            $classifications[] = 'campus_rail_conflict';
        }

        $providerRail = $request->provider_rail ?: self::PROVIDER_RAIL;
        $isPaidRequest = in_array($request->status, self::PAID_ACTIVE_STATUSES, true);
        if (! $isPaidRequest
            && in_array($studentLifecycleStatus, ['deferred', 'dropout', 'dropout_transfer'], true)) {
            $classifications[] = 'student_lifecycle_conflict';
        }
        $targets = $this->exactTargets($request, $isPaidRequest);
        if ($targets === []) {
            $classifications[] = 'unknown_link';
        } elseif ($isPaidRequest) {
            if ($request->payment !== null
                && Money::vnd((string) $request->payment->amount)->minor_amount !== Money::vnd((string) $request->amount)->minor_amount) {
                $classifications[] = 'amount_mismatch';
            }
        } else {
            $position = $this->settlementPositionReader->forPayableLines(array_column($targets, 'invoice_line_id'));
            $positions = collect($position->payable_line_breakdown)->keyBy('payable_line_id');
            $collectibleTotal = Money::zero();
            foreach ($targets as &$target) {
                $line = $positions->get((int) $target['invoice_line_id']);
                if ($line === null || ! $line->isValid() || $line->amounts === null) {
                    $classifications[] = 'unknown_link';

                    continue;
                }
                $collectible = $target['finance_charge_installment_id'] !== null
                    ? $this->installmentCollectible($target['finance_charge_installment_id'], $line->amounts->remaining, $classifications)
                    : $line->amounts->remaining->amount;
                if ($collectible === null) {
                    $classifications[] = 'unknown_link';

                    continue;
                }
                $target['collectible'] = $collectible;
                $collectibleTotal = $collectibleTotal->add(Money::vnd($collectible));
            }
            unset($target);

            if ($collectibleTotal->minor_amount !== Money::vnd((string) $request->amount)->minor_amount) {
                $classifications[] = 'amount_mismatch';
            }
        }

        if (in_array($request->status, [
            DngPaymentRequest::STATUS_PAID_UNINVOICED,
            DngPaymentRequest::STATUS_PAID_INVOICED,
            DngPaymentRequest::STATUS_RECONCILED,
        ], true) && $request->payment_id === null) {
            $classifications[] = 'paid_unbridged';
        }

        if ($request->status === DngPaymentRequest::STATUS_UNKNOWN_OUTCOME) {
            $classifications[] = 'provider_outcome_unknown';
        }
        if ($request->status === DngPaymentRequest::STATUS_NEEDS_REVIEW) {
            $classifications[] = 'needs_review';
        }

        $classifications = array_values(array_unique($classifications));
        if ($classifications === [] && $request->reservationTargets()->exists()) {
            $classifications = ['exact_link'];
        } elseif ($classifications === []) {
            $classifications = ['exact_link'];
        }

        if (in_array($request->status, self::UNPAID_ACTIVE_STATUSES, true)
            && ($request->billing_account_id === null || ! $request->reservationTargets()->exists())) {
            $classifications[] = 'backfill_pending';
        }

        return [
            'targets' => $targets,
            'classifications' => $classifications,
            'billing_account_id' => $billingAccountId,
            'campus_code' => $campusCode !== '' ? $campusCode : null,
            'provider_rail' => $providerRail,
            'fee_type' => (string) $request->fee_type,
        ];
    }

    /** @return list<array{invoice_line_id: int, finance_charge_id: int, finance_charge_installment_id: ?int, target_identity: string, identity: string, collectible: string}> */
    private function exactTargets(DngPaymentRequest $request, bool $includeHistoricalLines = false): array
    {
        $targets = $request->reservationTargets()->get();
        if ($targets->isNotEmpty()) {
            $lineCharges = InvoiceLine::query()
                ->whereIn('id', $targets->pluck('invoice_line_id'))
                ->pluck('charge_id', 'id');

            return $targets->map(static fn (DngPaymentRequestReservationTarget $target): array => [
                'invoice_line_id' => (int) $target->invoice_line_id,
                'finance_charge_id' => (int) $lineCharges->get($target->invoice_line_id),
                'finance_charge_installment_id' => $target->finance_charge_installment_id !== null ? (int) $target->finance_charge_installment_id : null,
                'target_identity' => (string) $target->target_identity,
                'identity' => (string) $target->target_identity,
                'collectible' => (string) $target->captured_collectible,
            ])->all();
        }

        $chargeIds = $request->chargeLinks()
            ->pluck('finance_charge_id')
            ->merge(FinanceChargeInstallment::query()->where('dng_payment_request_id', $request->id)->pluck('finance_charge_id'))
            ->filter()
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();
        if ($chargeIds->isEmpty()) {
            return $includeHistoricalLines ? $this->paidPaymentTargets($request) : [];
        }

        $installmentByCharge = FinanceChargeInstallment::query()
            ->where('dng_payment_request_id', $request->id)
            ->get()
            ->keyBy('finance_charge_id');
        $lines = InvoiceLine::query()
            ->whereIn('charge_id', $chargeIds)
            ->when(! $includeHistoricalLines, fn ($query) => $query->where('status', 'active'))
            ->get()
            ->groupBy('charge_id');

        $result = [];
        foreach ($chargeIds as $chargeId) {
            $chargeLines = $lines->get($chargeId, collect());
            if ($chargeLines->count() !== 1) {
                return [];
            }
            $line = $chargeLines->first();
            $installment = $installmentByCharge->get($chargeId);
            $identity = 'invoice_line:'.$line->id;
            if ($installment !== null) {
                $identity .= ':installment:'.$installment->id;
            }
            $result[] = [
                'invoice_line_id' => (int) $line->id,
                'finance_charge_id' => (int) $chargeId,
                'finance_charge_installment_id' => $installment?->id !== null ? (int) $installment->id : null,
                'target_identity' => $identity,
                'identity' => $identity,
                'collectible' => '0.00',
            ];
        }

        return $result;
    }

    /**
     * A paid request with no DNG target/pivot still has exact historical Finance
     * evidence when it is bridged to a Payment. Payment applications are ledger
     * rows, including reversals on void lines; they are not an amount-based guess.
     *
     * @return list<array{invoice_line_id: int, finance_charge_id: int, finance_charge_installment_id: null, target_identity: string, identity: string, collectible: string}>
     */
    private function paidPaymentTargets(DngPaymentRequest $request): array
    {
        if ($request->payment_id === null) {
            return [];
        }

        $lineIds = PaymentApplication::query()
            ->where('payment_id', $request->payment_id)
            ->pluck('invoice_line_id')
            ->map(static fn (int|string $lineId): int => (int) $lineId)
            ->filter(static fn (int $lineId): bool => $lineId > 0)
            ->unique()
            ->values();
        if ($lineIds->isEmpty()) {
            return [];
        }

        $chargeIdByLine = InvoiceLine::query()
            ->whereIn('id', $lineIds)
            ->pluck('charge_id', 'id');
        if ($chargeIdByLine->count() !== $lineIds->count()) {
            return [];
        }

        return $lineIds
            ->map(static function (int $lineId) use ($chargeIdByLine, $request): array {
                return [
                    'invoice_line_id' => $lineId,
                    'finance_charge_id' => (int) $chargeIdByLine->get($lineId),
                    'finance_charge_installment_id' => null,
                    'target_identity' => 'payment:'.$request->payment_id.':invoice_line:'.$lineId,
                    'identity' => 'payment:'.$request->payment_id.':invoice_line:'.$lineId,
                    'collectible' => '0.00',
                ];
            })
            ->all();
    }

    /** @param list<array{id: int, status: string, classifications: list<string>, action: string, billing_account_id: ?int, target_line_ids: list<int>}> $records */
    private function addConflictClassifications(array &$records): void
    {
        $bySlot = [];
        $byPayerFee = [];
        foreach ($records as $record) {
            if (! in_array($record['status'], self::UNPAID_ACTIVE_STATUSES, true)) {
                continue;
            }
            $inspection = $this->inspections[$record['id']];
            if ($inspection['billing_account_id'] === null || $inspection['campus_code'] === null) {
                continue;
            }
            $slot = $this->slotKey(
                $inspection['billing_account_id'],
                $inspection['campus_code'],
                (string) $inspection['provider_rail'],
                $inspection['fee_type'],
            );
            $bySlot[$slot][] = $record['id'];
            $byPayerFee[$inspection['billing_account_id'].':'.$inspection['fee_type']][] = $record['id'];
        }

        foreach ($bySlot as $ids) {
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    $this->inspections[$id]['classifications'][] = 'slot_conflict';
                }
            }
        }
        foreach ($byPayerFee as $ids) {
            $rails = collect($ids)->map(fn (int $id): string => (string) $this->inspections[$id]['provider_rail'].'|'.$this->inspections[$id]['campus_code'])->unique();
            if (count($ids) > 1 && $rails->count() > 1) {
                foreach ($ids as $id) {
                    $this->inspections[$id]['classifications'][] = 'campus_rail_conflict';
                }
            }
        }
    }

    /** @param list<array{id: int, status: string, classifications: list<string>, action: string, billing_account_id: ?int, target_line_ids: list<int>}> $records @return array<string, int> */
    private function counts(array $records): array
    {
        $counts = [];
        foreach ($records as $record) {
            foreach (array_unique($this->inspections[$record['id']]['classifications']) as $classification) {
                $counts[$classification] = ($counts[$classification] ?? 0) + 1;
            }
        }
        $counts['exact_link'] ??= 0;

        return $counts;
    }

    private function slotKey(int $billingAccountId, string $campusCode, string $providerRail, string $feeType): string
    {
        return implode(':', [$providerRail, $campusCode, $billingAccountId, $feeType]);
    }

    /** @param list<string> $classifications */
    private function actionFor(array $classifications): string
    {
        if (in_array('student_lifecycle_conflict', $classifications, true)) {
            return 'cancel_collection';
        }
        if (in_array('paid_unbridged', $classifications, true)) {
            return 'paid_bridge';
        }
        if (in_array('provider_outcome_unknown', $classifications, true)) {
            return 'cancel_or_reconcile';
        }
        if (in_array('unknown_link', $classifications, true)
            || in_array('campus_rail_conflict', $classifications, true)
            || in_array('amount_mismatch', $classifications, true)
            || in_array('needs_review', $classifications, true)
            || in_array('backfill_pending', $classifications, true)) {
            return 'repair';
        }
        if (in_array('unmatched_receipt', $classifications, true)) {
            return 'unknown';
        }

        return 'none';
    }

    /** @param list<string> $classifications */
    private function installmentCollectible(int $installmentId, Money $remaining, array &$classifications): ?string
    {
        $installment = FinanceChargeInstallment::query()->find($installmentId);
        if ($installment === null || ! in_array($installment->status, [
            FinanceChargeInstallment::STATUS_PENDING,
            FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        ], true)) {
            $classifications[] = 'unknown_link';

            return null;
        }

        $amount = Money::vnd((string) $installment->amount);
        if ($amount->isGreaterThan($remaining)) {
            $classifications[] = 'amount_mismatch';

            return null;
        }

        return $amount->amount;
    }
}
