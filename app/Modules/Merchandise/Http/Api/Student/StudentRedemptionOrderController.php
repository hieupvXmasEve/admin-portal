<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Modules\Merchandise\Exceptions\RedemptionStateConflictException;
use App\Modules\Merchandise\Http\Requests\Student\CancelRedemptionOrderRequest;
use App\Modules\Merchandise\Http\Requests\Student\StoreRedemptionOrderRequest;
use App\Modules\Merchandise\Models\RedemptionOrder;
use App\Modules\Merchandise\Support\RedemptionService;
use App\Services\GoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Student-owned redemption orders. Every method scopes to the authenticated
 * student's own orders — a 404 (not 403) is returned for another student's
 * order id so this endpoint does not leak order existence across students.
 */
class StudentRedemptionOrderController extends Controller
{
    public function __construct(
        private readonly RedemptionService $redemptionService,
        private readonly GoldService $goldService,
    ) {}

    public function store(StoreRedemptionOrderRequest $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        try {
            $order = $this->redemptionService->createOrder(
                $student,
                $request->lines(),
                (string) $request->input('method'),
                $request->input('shipping_address'),
                $request->header('Idempotency-Key'),
                // Self-service checkout: no staff performer, the ledger row
                // is attributed to the student's own action (performed_by null).
                null,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }

        return ApiResponse::success(
            $this->present($order->load('items')),
            message: 'Redemption order created',
            status: Response::HTTP_CREATED
        );
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $query = RedemptionOrder::where('student_id', $student->id)
            ->with($this->itemImageEagerLoad())
            ->orderByDesc('created_at');

        $status = $request->input('status');
        if ($status !== null) {
            if (! in_array($status, RedemptionOrder::STATUSES, true)) {
                return ApiResponse::validationError(['status' => ['Invalid status filter']]);
            }
            $query->where('status', $status);
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 50));
        $orders = $query->paginate($perPage);
        $orders->getCollection()->transform(fn (RedemptionOrder $order) => $this->present($order));

        return ApiResponse::paginated($orders);
    }

    public function show(Request $request, RedemptionOrder $redemptionOrder): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if ((int) $redemptionOrder->student_id !== (int) $student->id) {
            return ApiResponse::notFound('Redemption order not found');
        }

        $redemptionOrder->load($this->itemImageEagerLoad());

        return ApiResponse::success($this->present($redemptionOrder, includeTimeline: true));
    }

    public function cancel(CancelRedemptionOrderRequest $request, RedemptionOrder $redemptionOrder): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if ((int) $redemptionOrder->student_id !== (int) $student->id) {
            return ApiResponse::notFound('Redemption order not found');
        }

        $reason = $request->input('reason');

        try {
            $order = match ($redemptionOrder->status) {
                RedemptionOrder::STATUS_PENDING_REVIEW => $this->redemptionService->cancelPendingReview(
                    $redemptionOrder->id,
                    $reason,
                    null,
                ),
                RedemptionOrder::STATUS_APPROVED,
                RedemptionOrder::STATUS_READY_FOR_COLLECTION,
                RedemptionOrder::STATUS_PICKUP_OVERDUE => $this->redemptionService->requestCancellation(
                    $redemptionOrder->id,
                    trim((string) $reason) !== '' ? (string) $reason : 'Requested by student',
                    (int) $student->id,
                ),
                default => throw new RedemptionStateConflictException('This order can no longer be cancelled'),
            };
        } catch (InvalidArgumentException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (RedemptionStateConflictException $e) {
            return ApiResponse::conflict($e->getMessage());
        }

        return ApiResponse::success($this->present($order->load('items')));
    }

    public function dashboard(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $totalRedeemed = (int) RedemptionOrder::where('student_id', $student->id)
            ->whereIn('status', RedemptionOrder::NON_REVERSED_STATUSES)
            ->sum('total_gold');

        return ApiResponse::success([
            'current_gold' => $this->goldService->getBalance($student),
            'total_redeemed' => $totalRedeemed,
            'redemption_orders_count' => RedemptionOrder::where('student_id', $student->id)->count(),
        ]);
    }

    /** @return array<int, string> */
    private function itemImageEagerLoad(): array
    {
        return ['items.variant.merchandise.images'];
    }

    /** @return array<string, mixed> */
    private function present(RedemptionOrder $order, bool $includeTimeline = false): array
    {
        $data = [
            'id' => $order->id,
            'code' => $order->code,
            'status' => $order->status,
            'previous_status' => $order->previous_status,
            'method' => $order->method,
            'total_gold' => (int) $order->total_gold,
            'items' => $order->items->map(fn ($item) => [
                'merchandise_name' => $item->merchandise_name,
                'variant_label' => $item->variant_label,
                'gold_price_each' => (int) $item->gold_price_each,
                'line_total' => (int) $item->line_total,
                'quantity' => (int) $item->quantity,
                // Snapshot fields never store an image — resolve today's
                // primary image via the (possibly since-archived) variant's
                // merchandise. Null once the variant/merchandise is gone.
                'image' => $item->variant?->merchandise?->images->firstWhere('is_primary', true)?->path
                    ?? $item->variant?->merchandise?->images->first()?->path,
            ])->values(),
            'collection' => $order->method === RedemptionOrder::METHOD_PICKUP ? [
                'location' => $order->collection_location,
                'ready_at' => $order->ready_at?->toIso8601String(),
                'deadline' => $order->collection_deadline?->toIso8601String(),
                'collected_at' => $order->collected_at?->toIso8601String(),
            ] : null,
            'shipping' => $order->method === RedemptionOrder::METHOD_SHIPPING ? [
                'address' => $order->shipping_address,
                'shipped_at' => $order->shipped_at?->toIso8601String(),
                'note' => $order->shipping_note,
            ] : null,
            'reject_reason' => $order->reject_reason,
            // Gate on status/cancelled_at too, not just cancellation_requested_at:
            // the student "cancel while still pending_review" path
            // (RedemptionService::cancelPendingReview) cancels immediately —
            // it sets cancellation_reason + cancelled_at but never "requests"
            // one, so requested_at alone would drop the reason from this API.
            'cancellation' => ($order->status === RedemptionOrder::STATUS_CANCELLED || $order->cancellation_requested_at !== null) ? [
                'requested_at' => $order->cancellation_requested_at?->toIso8601String(),
                'reason' => $order->cancellation_reason,
                'result' => $order->cancellation_result,
                'handled_at' => $order->cancellation_handled_at?->toIso8601String(),
                'note' => $order->cancellation_note,
                'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            ] : null,
            // Whenever the order ends in 'rejected' or 'cancelled', the full
            // order total was refunded (RedemptionService::refund is called
            // on every path into either status) — surface it explicitly so
            // the student isn't left guessing whether their Gold came back.
            'refunded_gold' => in_array($order->status, [RedemptionOrder::STATUS_REJECTED, RedemptionOrder::STATUS_CANCELLED], true)
                ? (int) $order->total_gold
                : null,
            'created_at' => $order->created_at?->toIso8601String(),
        ];

        if ($includeTimeline) {
            $data['timeline'] = $this->timeline($order);
        }

        return $data;
    }

    /**
     * Best-effort timeline derived from the stage timestamp columns already
     * on the order — there is no separate event-log table (previous_status
     * plus these columns is the audit trail, per the schema design).
     *
     * @return list<array{event: string, at: string}>
     */
    private function timeline(RedemptionOrder $order): array
    {
        $stops = [
            ['event' => 'order_placed', 'at' => $order->created_at],
            ['event' => 'ready_for_collection', 'at' => $order->ready_at],
            ['event' => 'collected', 'at' => $order->collected_at],
            ['event' => 'shipped', 'at' => $order->shipped_at],
            ['event' => 'cancellation_requested', 'at' => $order->cancellation_requested_at],
            ['event' => 'cancellation_handled', 'at' => $order->cancellation_handled_at],
            ['event' => 'cancelled', 'at' => $order->cancelled_at],
        ];

        return collect($stops)
            ->filter(fn (array $stop) => $stop['at'] !== null)
            ->map(fn (array $stop) => ['event' => $stop['event'], 'at' => $stop['at']->toIso8601String()])
            ->sortBy('at')
            ->values()
            ->all();
    }
}
