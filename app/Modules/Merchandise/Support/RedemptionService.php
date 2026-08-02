<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Models\GoldTransaction;
use App\Models\Merchandise;
use App\Models\MerchandiseVariant;
use App\Models\RedemptionOrder;
use App\Models\RedemptionOrderItem;
use App\Models\StockMovement;
use App\Models\Student;
use App\Modules\Merchandise\Exceptions\RedemptionStateConflictException;
use App\Services\GoldService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Redemption order lifecycle: checkout, refund, and every staff/student
 * status transition.
 *
 * GLOBAL LOCK ORDER — identical in every method here that touches both the
 * Gold wallet and merchandise variants in one transaction:
 *   1. Gold wallet (GoldService::lockWalletForUpdate / deductGold / addGold)
 *   2. Merchandise variants, ordered by merchandise_variant_id ASC
 * A multi-item cart locks variants in id order, not cart order, so two carts
 * sharing items in a different order (A=[7,3], B=[3,7]) cannot deadlock.
 *
 * Every status transition locks the order row FOR UPDATE and checks the
 * current status in PHP before mutating. That row lock is held for the rest
 * of the transaction, so no other connection can read-for-update or write
 * this row until commit — the in-PHP check is therefore equivalent to (and
 * simpler than) a conditional `UPDATE ... WHERE status = :expected`: a second
 * concurrent transition blocks on the lock, then sees the already-updated
 * status once it acquires it and is rejected. This is what stops two staff
 * both approving/refunding the same order.
 */
class RedemptionService
{
    private const CODE_MAX_ATTEMPTS = 5;

    private const DEFAULT_COLLECTION_DEADLINE_DAYS = 14;

    public function __construct(
        private readonly GoldService $goldService,
        private readonly StockService $stockService,
        private readonly RedemptionNotificationPublisher $notifications,
    ) {}

    /**
     * Create a redemption order: locks the wallet, locks the requested
     * variants (ascending id), validates availability, snapshots line items,
     * then deducts Gold and stock. Atomic — any failure rolls back the order,
     * the Gold deduction, and every stock decrement together.
     *
     * @param  list<array{variant_id:int, quantity:int}>  $lines
     */
    public function createOrder(
        Student $student,
        array $lines,
        string $method,
        ?string $shippingAddress,
        ?string $idempotencyKey,
        ?int $performedBy = null,
    ): RedemptionOrder {
        if (! in_array($method, RedemptionOrder::METHODS, true)) {
            throw new InvalidArgumentException("Invalid redemption method: {$method}");
        }

        if ($method === RedemptionOrder::METHOD_SHIPPING && trim((string) $shippingAddress) === '') {
            throw new InvalidArgumentException('Shipping address is required for shipping orders');
        }

        $quantities = $this->normalizeLines($lines);

        // Common-case idempotency replay: a client retrying a dropped
        // response finds its own already-committed order and stops here
        // without touching Gold or stock again.
        if ($idempotencyKey !== null) {
            $existing = RedemptionOrder::where('student_id', $student->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $variantIds = array_keys($quantities);

        for ($attempt = 1; ; $attempt++) {
            $code = $this->generateOrderCode();

            try {
                return DB::transaction(function () use (
                    $student, $quantities, $variantIds, $method, $shippingAddress, $idempotencyKey, $performedBy, $code
                ) {
                    // 1. Gold wallet — locked first (global lock order).
                    $wallet = $this->goldService->lockWalletForUpdate($student);

                    // 2. Merchandise variants — locked ascending by id.
                    $variants = MerchandiseVariant::with('merchandise')
                        ->whereIn('id', $variantIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    if ($variants->count() !== count($variantIds)) {
                        throw new InvalidArgumentException('One or more merchandise variants do not exist');
                    }

                    $totalGold = 0;
                    $lineData = [];

                    foreach ($quantities as $variantId => $quantity) {
                        /** @var MerchandiseVariant $variant */
                        $variant = $variants->get($variantId);
                        $merchandise = $variant->merchandise;

                        if (! $variant->is_active) {
                            throw new InvalidArgumentException("Variant {$variantId} is not active");
                        }

                        if ($merchandise === null || $merchandise->status !== Merchandise::STATUS_ACTIVE) {
                            throw new InvalidArgumentException("Merchandise for variant {$variantId} is not available");
                        }

                        if ((int) $variant->campus_id !== (int) $student->campus_id) {
                            throw new InvalidArgumentException("Variant {$variantId} is not available at your campus");
                        }

                        if ((int) $variant->stock_quantity < $quantity) {
                            throw new InvalidArgumentException("Insufficient stock for variant {$variantId}");
                        }

                        $priceEach = (int) $merchandise->gold_price;
                        $lineTotal = $priceEach * $quantity;
                        $totalGold += $lineTotal;

                        $lineData[] = [
                            'variant' => $variant,
                            'merchandise_name' => $merchandise->name,
                            'variant_label' => $this->variantLabel($variant),
                            'gold_price_each' => $priceEach,
                            'line_total' => $lineTotal,
                            'quantity' => $quantity,
                        ];
                    }

                    if ((int) $wallet->balance < $totalGold) {
                        throw new InvalidArgumentException('Insufficient Gold balance');
                    }

                    // Order row goes in FIRST: a code/idempotency collision
                    // surfaces here, before any Gold or stock work happens.
                    $order = RedemptionOrder::create([
                        'student_id' => $student->id,
                        'campus_id' => $student->campus_id,
                        'code' => $code,
                        'status' => RedemptionOrder::STATUS_PENDING_REVIEW,
                        'method' => $method,
                        'total_gold' => $totalGold,
                        'idempotency_key' => $idempotencyKey,
                        'shipping_address' => $method === RedemptionOrder::METHOD_SHIPPING ? $shippingAddress : null,
                    ]);

                    foreach ($lineData as $line) {
                        RedemptionOrderItem::create([
                            'redemption_order_id' => $order->id,
                            'merchandise_variant_id' => $line['variant']->id,
                            'merchandise_name' => $line['merchandise_name'],
                            'variant_label' => $line['variant_label'],
                            'gold_price_each' => $line['gold_price_each'],
                            'line_total' => $line['line_total'],
                            'quantity' => $line['quantity'],
                        ]);
                    }

                    $this->goldService->deductGold(
                        $student,
                        $totalGold,
                        GoldTransaction::SOURCE_REDEMPTION_ORDER,
                        $order->id,
                        "Redemption order {$order->code}",
                        $performedBy,
                        GoldTransaction::TYPE_REDEMPTION,
                    );

                    foreach ($lineData as $line) {
                        $this->stockService->adjustStock(
                            $line['variant'],
                            -$line['quantity'],
                            StockMovement::TYPE_REDEMPTION_OUT,
                            $performedBy,
                            "Redemption order {$order->code}",
                            $order->id,
                        );
                    }

                    $order = $order->fresh(['items']);
                    $this->notifications->orderSubmitted($order);

                    return $order;
                }, 3);
            } catch (QueryException $e) {
                if ($idempotencyKey !== null && $this->isIdempotencyCollision($e)) {
                    $existing = RedemptionOrder::where('student_id', $student->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing !== null) {
                        return $existing;
                    }
                }

                if (! $this->isCodeCollision($e) || $attempt >= self::CODE_MAX_ATTEMPTS) {
                    throw $e;
                }

                // else: code collision — loop, a fresh code is drawn above.
            }
        }
    }

    public function approve(int $orderId, ?int $performedBy): RedemptionOrder
    {
        return $this->transition($orderId, [RedemptionOrder::STATUS_PENDING_REVIEW], function (RedemptionOrder $order) {
            $order->previous_status = $order->status;
            $order->status = RedemptionOrder::STATUS_APPROVED;
            $this->notifications->orderApproved($order);
        });
    }

    public function reject(int $orderId, string $reason, ?int $performedBy): RedemptionOrder
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reject reason is required');
        }

        return $this->transition($orderId, [RedemptionOrder::STATUS_PENDING_REVIEW], function (RedemptionOrder $order) use ($reason, $performedBy) {
            $order->previous_status = $order->status;
            $order->status = RedemptionOrder::STATUS_REJECTED;
            $order->reject_reason = $reason;
            $this->refund($order, "Rejected: {$reason}", $performedBy);
            $this->notifications->orderRejected($order);
        });
    }

    /**
     * Student self-service cancel while still awaiting review — no staff
     * approval loop needed since nothing has shipped or been collected yet.
     */
    public function cancelPendingReview(int $orderId, ?string $reason, ?int $performedBy): RedemptionOrder
    {
        return $this->transition($orderId, [RedemptionOrder::STATUS_PENDING_REVIEW], function (RedemptionOrder $order) use ($reason, $performedBy) {
            $order->previous_status = $order->status;
            $order->status = RedemptionOrder::STATUS_CANCELLED;
            $order->cancellation_reason = $reason;
            $order->cancelled_at = now();
            $this->refund($order, $reason ?? 'Cancelled by student', $performedBy);
            $this->notifications->orderCancelled($order);
        });
    }

    public function setReadyForCollection(int $orderId, string $location, ?Carbon $deadline, ?int $performedBy): RedemptionOrder
    {
        if (trim($location) === '') {
            throw new InvalidArgumentException('A collection location is required');
        }

        return $this->transition(
            $orderId,
            [RedemptionOrder::STATUS_APPROVED, RedemptionOrder::STATUS_PICKUP_OVERDUE],
            function (RedemptionOrder $order) use ($location, $deadline) {
                $order->previous_status = $order->status;
                $order->status = RedemptionOrder::STATUS_READY_FOR_COLLECTION;
                $order->collection_location = $location;
                $order->ready_at = now();
                $order->collection_deadline = $deadline ?? now()->addDays(self::DEFAULT_COLLECTION_DEADLINE_DAYS);
                $this->notifications->orderReadyForCollection($order);
            }
        );
    }

    public function extendDeadline(int $orderId, Carbon $newDeadline, ?int $performedBy): RedemptionOrder
    {
        return $this->transition(
            $orderId,
            [RedemptionOrder::STATUS_READY_FOR_COLLECTION],
            function (RedemptionOrder $order) use ($newDeadline) {
                $order->collection_deadline = $newDeadline;
                $this->notifications->deadlineExtended($order);
            }
        );
    }

    public function confirmCollected(int $orderId, ?int $performedBy): RedemptionOrder
    {
        return $this->transition(
            $orderId,
            [RedemptionOrder::STATUS_READY_FOR_COLLECTION, RedemptionOrder::STATUS_PICKUP_OVERDUE],
            function (RedemptionOrder $order) use ($performedBy) {
                $order->previous_status = $order->status;
                $order->status = RedemptionOrder::STATUS_COLLECTED;
                $order->collected_at = now();
                $order->collected_confirmed_by = $performedBy;
                $this->notifications->orderCollected($order);
            }
        );
    }

    public function markShipped(int $orderId, ?string $note, ?int $performedBy): RedemptionOrder
    {
        return $this->transition(
            $orderId,
            [RedemptionOrder::STATUS_APPROVED],
            function (RedemptionOrder $order) use ($note, $performedBy) {
                $order->previous_status = $order->status;
                $order->status = RedemptionOrder::STATUS_SHIPPED;
                $order->shipped_at = now();
                $order->shipped_by = $performedBy;
                $order->shipping_note = $note;
                $this->notifications->orderShipped($order);
            }
        );
    }

    /** Staff mark a ready-for-collection order overdue. Manual — no cron (D7). */
    public function markOverdue(int $orderId, ?int $performedBy): RedemptionOrder
    {
        return $this->transition(
            $orderId,
            [RedemptionOrder::STATUS_READY_FOR_COLLECTION],
            function (RedemptionOrder $order) {
                $order->previous_status = $order->status;
                $order->status = RedemptionOrder::STATUS_PICKUP_OVERDUE;
                $this->notifications->orderOverdue($order);
            }
        );
    }

    /**
     * Staff-direct cancel of an overdue pickup. Distinct from
     * requestCancellation/handleCancellation: pickup_overdue is a
     * staff-flagged state already, so staff can cancel it outright without a
     * separate request/approve round trip.
     */
    public function cancelOverdue(int $orderId, ?string $reason, ?int $performedBy): RedemptionOrder
    {
        return $this->transition(
            $orderId,
            [RedemptionOrder::STATUS_PICKUP_OVERDUE],
            function (RedemptionOrder $order) use ($reason, $performedBy) {
                $order->previous_status = $order->status;
                $order->status = RedemptionOrder::STATUS_CANCELLED;
                $order->cancellation_reason = $reason;
                $order->cancelled_at = now();
                $this->refund($order, $reason ?? 'Cancelled: pickup overdue', $performedBy);
                $this->notifications->orderCancelled($order);
            }
        );
    }

    /** Student requests cancellation once the order is past pending_review. */
    public function requestCancellation(int $orderId, string $reason, int $requestedByStudentId): RedemptionOrder
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A cancellation reason is required');
        }

        return $this->transition(
            $orderId,
            [
                RedemptionOrder::STATUS_APPROVED,
                RedemptionOrder::STATUS_READY_FOR_COLLECTION,
                RedemptionOrder::STATUS_PICKUP_OVERDUE,
            ],
            function (RedemptionOrder $order) use ($reason, $requestedByStudentId) {
                $order->previous_status = $order->status;
                $order->status = RedemptionOrder::STATUS_CANCELLATION_REQUESTED;
                $order->cancellation_reason = $reason;
                $order->cancellation_requested_by = $requestedByStudentId;
                $order->cancellation_requested_at = now();
                $this->notifications->cancellationRequested($order);
            }
        );
    }

    /** Staff accepts (refund) or rejects (revert to previous_status) a cancellation request. */
    public function handleCancellation(int $orderId, bool $accept, ?string $note, ?int $performedBy): RedemptionOrder
    {
        return $this->transition(
            $orderId,
            [RedemptionOrder::STATUS_CANCELLATION_REQUESTED],
            function (RedemptionOrder $order) use ($accept, $note, $performedBy) {
                // The status the order was in before it entered
                // cancellation_requested — written by requestCancellation()
                // and still intact here since this method has not yet
                // touched previous_status.
                $priorStatus = $order->previous_status;

                $order->cancellation_handled_by = $performedBy;
                $order->cancellation_handled_at = now();
                $order->cancellation_note = $note;

                if ($accept) {
                    $order->cancellation_result = RedemptionOrder::CANCELLATION_RESULT_ACCEPTED;
                    $order->previous_status = $order->status;
                    $order->status = RedemptionOrder::STATUS_CANCELLED;
                    $order->cancelled_at = now();
                    $this->refund($order, $order->cancellation_reason ?? 'Cancellation accepted', $performedBy);
                    $this->notifications->orderCancelled($order);
                } else {
                    $order->cancellation_result = RedemptionOrder::CANCELLATION_RESULT_REJECTED;
                    $order->previous_status = $order->status;
                    $order->status = $priorStatus ?? RedemptionOrder::STATUS_APPROVED;
                    $this->notifications->cancellationRejected($order);
                }
            }
        );
    }

    /**
     * Lock the order row, verify the current status allows this transition,
     * hand it to $mutate for the field changes, then persist. See the class
     * docblock for why the row lock alone is a sufficient concurrency guard.
     *
     * @param  list<string>  $allowedFrom
     */
    private function transition(int $orderId, array $allowedFrom, callable $mutate): RedemptionOrder
    {
        return DB::transaction(function () use ($orderId, $allowedFrom, $mutate) {
            $order = RedemptionOrder::whereKey($orderId)->lockForUpdate()->first();

            if ($order === null) {
                throw new InvalidArgumentException("Redemption order {$orderId} not found");
            }

            if (! in_array($order->status, $allowedFrom, true)) {
                throw new RedemptionStateConflictException(
                    "Redemption order {$orderId} is '{$order->status}'; this action is not allowed from that state"
                );
            }

            $mutate($order);
            $order->save();

            return $order;
        }, 3);
    }

    /**
     * Symmetric refund: adds the order's Gold back and restores every line's
     * stock. Called from inside an already-locked transition() transaction,
     * so it inherits the same wallet-then-variants lock order. The
     * `redemption_dedup_key` partial-unique index on gold_transactions is a
     * DB-level backstop against this ever running twice for one order, on
     * top of the state guard in transition().
     */
    private function refund(RedemptionOrder $order, string $reason, ?int $performedBy): void
    {
        $student = Student::findOrFail($order->student_id);

        $this->goldService->addGold(
            $student,
            (int) $order->total_gold,
            GoldTransaction::SOURCE_REDEMPTION_ORDER,
            $order->id,
            $reason,
            $performedBy,
            GoldTransaction::TYPE_REDEMPTION_REFUND,
        );

        $items = $order->items()->with('variant')->orderBy('merchandise_variant_id')->get();

        foreach ($items as $item) {
            if ($item->variant === null) {
                // merchandise_variant_id is a restrict-on-delete FK, so a
                // referenced variant cannot actually be removed — defensive
                // only, mirrors StockService's own "unreachable" guards.
                continue;
            }

            $this->stockService->adjustStock(
                $item->variant,
                (int) $item->quantity,
                StockMovement::TYPE_REDEMPTION_REFUND,
                $performedBy,
                $reason,
                $order->id,
            );
        }
    }

    /**
     * @param  list<array{variant_id:int, quantity:int}>  $lines
     * @return array<int, int> variant_id => quantity, ascending by variant_id
     */
    private function normalizeLines(array $lines): array
    {
        $quantities = [];

        foreach ($lines as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($variantId <= 0 || $quantity <= 0) {
                throw new InvalidArgumentException('Each line requires a valid variant_id and a positive quantity');
            }

            $quantities[$variantId] = ($quantities[$variantId] ?? 0) + $quantity;
        }

        if ($quantities === []) {
            throw new InvalidArgumentException('Redemption order requires at least one line item');
        }

        ksort($quantities);

        return $quantities;
    }

    private function variantLabel(MerchandiseVariant $variant): ?string
    {
        $parts = array_filter([$variant->color, $variant->size], fn ($v) => $v !== null && $v !== '');

        return $parts === [] ? null : implode(' / ', $parts);
    }

    private function generateOrderCode(): string
    {
        // RT-9: random suffix, not sequential — two students checking out in
        // the same second cannot both compute the same "next" number and
        // race an insert that would roll back the whole money transaction.
        $random = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        return 'MRD-'.now()->format('Ym').'-'.$random;
    }

    private function isCodeCollision(QueryException $e): bool
    {
        return $e->getCode() === '23000' && str_contains($e->getMessage(), 'redemption_orders_code_unique');
    }

    private function isIdempotencyCollision(QueryException $e): bool
    {
        return $e->getCode() === '23000' && str_contains($e->getMessage(), 'redemption_orders_student_idempotency_unique');
    }
}
