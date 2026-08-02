<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\RedemptionOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Inertia pages for the staff redemption-order review queue. Reads
 * only — every state transition (approve/reject/ready-for-collection/...)
 * goes through the JSON API controller (Http/Api/RedemptionOrderController),
 * called from the Vue pages via fetch() + router.reload(), same split as
 * MerchandiseWebController.
 */
class RedemptionOrderWebController extends Controller
{
    public function index(Request $request): Response
    {
        $campusIds = $this->grantedCampusIds((int) Auth::id(), 'view_redemption_order');

        $filters = $request->only(['status', 'per_page']);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $orders = RedemptionOrder::query()
            ->when($campusIds === [], fn ($query) => $query->whereRaw('1 = 0'))
            ->when($campusIds !== [], fn ($query) => $query->whereIn('campus_id', $campusIds))
            ->with('student:id,student_id,full_name')
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $paginationData = [
            'data' => $orders->getCollection()->map(fn (RedemptionOrder $order) => [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $order->status,
                'method' => $order->method,
                'total_gold' => (int) $order->total_gold,
                'items_count' => $order->items_count,
                'student' => [
                    'student_id' => $order->student?->student_id,
                    'full_name' => $order->student?->full_name,
                ],
                'created_at' => $order->created_at?->toIso8601String(),
            ])->all(),
            'links' => $orders->linkCollection()->toArray(),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'from' => $orders->firstItem(),
            'to' => $orders->lastItem(),
            'prev_page_url' => $orders->previousPageUrl(),
            'next_page_url' => $orders->nextPageUrl(),
        ];

        return Inertia::render('RedemptionOrders/Index', [
            'orders' => $paginationData,
            'filters' => $filters,
            'statuses' => RedemptionOrder::STATUSES,
        ]);
    }

    public function show(RedemptionOrder $redemptionOrder): Response
    {
        $this->authorize('view', $redemptionOrder);

        $redemptionOrder->load([
            'items.variant.merchandise.images',
            'student:id,student_id,full_name,email,phone,campus_id',
            'student.campus:id,name',
            'collectedConfirmedBy:id,name',
            'shippedBy:id,name',
        ]);

        return Inertia::render('RedemptionOrders/Show', [
            'order' => $redemptionOrder,
        ]);
    }

    /** @return list<int> */
    private function grantedCampusIds(int $userId, string $permission): array
    {
        return DB::table('campus_user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'campus_user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('campus_user_roles.user_id', $userId)
            ->where('permissions.code', $permission)
            ->distinct()
            ->pluck('campus_user_roles.campus_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
