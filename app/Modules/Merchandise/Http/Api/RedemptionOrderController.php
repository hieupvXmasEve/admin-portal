<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\RedemptionOrder;
use App\Modules\Merchandise\Exceptions\RedemptionStateConflictException;
use App\Modules\Merchandise\Support\RedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Staff redemption order state transitions. Every action authorizes via
 * RedemptionOrderPolicy, which resolves the campus from the order's snapshot
 * campus_id (RT-13) — a staff member only acts on orders at a campus they hold
 * the matching permission at.
 *
 * Reading is not here: the queue and the detail page are served by
 * RedemptionOrderWebController (Inertia). This class only mutates.
 */
class RedemptionOrderController extends Controller
{
    public function __construct(private readonly RedemptionService $redemptionService) {}

    public function approve(RedemptionOrder $redemptionOrder): JsonResponse
    {
        $this->authorize('approve', $redemptionOrder);

        return $this->handle(fn () => $this->redemptionService->approve($redemptionOrder->id, Auth::id()));
    }

    public function reject(Request $request, RedemptionOrder $redemptionOrder): JsonResponse
    {
        $this->authorize('reject', $redemptionOrder);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->handle(fn () => $this->redemptionService->reject($redemptionOrder->id, $validated['reason'], Auth::id()));
    }

    public function setReadyForCollection(Request $request, RedemptionOrder $redemptionOrder): JsonResponse
    {
        $this->authorize('process', $redemptionOrder);
        $validated = $request->validate([
            'location' => ['required', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
        ]);

        $deadline = isset($validated['deadline']) ? Carbon::parse($validated['deadline']) : null;

        return $this->handle(fn () => $this->redemptionService->setReadyForCollection(
            $redemptionOrder->id, $validated['location'], $deadline, Auth::id()
        ));
    }

    public function extendDeadline(Request $request, RedemptionOrder $redemptionOrder): JsonResponse
    {
        $this->authorize('process', $redemptionOrder);
        $validated = $request->validate(['deadline' => ['required', 'date']]);

        return $this->handle(fn () => $this->redemptionService->extendDeadline(
            $redemptionOrder->id, Carbon::parse($validated['deadline']), Auth::id()
        ));
    }

    public function confirmCollected(RedemptionOrder $redemptionOrder): JsonResponse
    {
        $this->authorize('confirmCollection', $redemptionOrder);

        return $this->handle(fn () => $this->redemptionService->confirmCollected($redemptionOrder->id, Auth::id()));
    }

    public function markShipped(Request $request, RedemptionOrder $redemptionOrder): JsonResponse
    {
        $this->authorize('markShipped', $redemptionOrder);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->handle(fn () => $this->redemptionService->markShipped(
            $redemptionOrder->id, $validated['note'] ?? null, Auth::id()
        ));
    }

    public function markOverdue(RedemptionOrder $redemptionOrder): JsonResponse
    {
        $this->authorize('confirmCollection', $redemptionOrder);

        return $this->handle(fn () => $this->redemptionService->markOverdue($redemptionOrder->id, Auth::id()));
    }

    public function cancelOverdue(Request $request, RedemptionOrder $redemptionOrder): JsonResponse
    {
        $this->authorize('cancel', $redemptionOrder);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->handle(fn () => $this->redemptionService->cancelOverdue(
            $redemptionOrder->id, $validated['reason'] ?? null, Auth::id()
        ));
    }

    public function handleCancellation(Request $request, RedemptionOrder $redemptionOrder): JsonResponse
    {
        $this->authorize('cancel', $redemptionOrder);
        $validated = $request->validate([
            'accept' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->handle(fn () => $this->redemptionService->handleCancellation(
            $redemptionOrder->id, (bool) $validated['accept'], $validated['note'] ?? null, Auth::id()
        ));
    }

    private function handle(callable $action): JsonResponse
    {
        try {
            $order = $action();
        } catch (InvalidArgumentException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (RedemptionStateConflictException $e) {
            return ApiResponse::conflict($e->getMessage());
        }

        return ApiResponse::success($order->load('items'));
    }
}
