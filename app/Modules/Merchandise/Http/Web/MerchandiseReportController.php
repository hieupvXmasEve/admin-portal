<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Campus;
use App\Models\RedemptionOrder;
use App\Modules\Merchandise\Http\Requests\ListMerchandiseReportRequest;
use App\Modules\Merchandise\Queries\Reports\GoldUsedAndRefundedReportQuery;
use App\Modules\Merchandise\Queries\Reports\MostRedeemedMerchandiseReportQuery;
use App\Modules\Merchandise\Queries\Reports\OrdersByStatusReportQuery;
use App\Modules\Merchandise\Queries\Reports\StockByCampusReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Read-only admin reports over Phase 2-3 merchandise data (RT-13: campus
 * scope resolved from the caller's GRANTED campuses, not the session's
 * current_campus_id). The Inertia route renders the page shell; the JSON
 * route serves the same payload for filter refreshes and is what feature
 * tests exercise directly (a full Inertia render needs a Vite build, which
 * this test environment does not have).
 */
class MerchandiseReportController extends Controller
{
    private const REPORT_PERMISSION = 'view_merchandise_report';

    public function __construct(
        private readonly OrdersByStatusReportQuery $ordersByStatusQuery,
        private readonly GoldUsedAndRefundedReportQuery $goldSummaryQuery,
        private readonly MostRedeemedMerchandiseReportQuery $mostRedeemedQuery,
        private readonly StockByCampusReportQuery $stockQuery,
    ) {}

    public function index(ListMerchandiseReportRequest $request): Response
    {
        return Inertia::render('Merchandise/Reports/Index', $this->buildPayload($request));
    }

    public function data(ListMerchandiseReportRequest $request): JsonResponse
    {
        return ApiResponse::success($this->buildPayload($request));
    }

    /** @return array<string, mixed> */
    private function buildPayload(ListMerchandiseReportRequest $request): array
    {
        $validated = $request->validated();
        $grantedCampusIds = $this->grantedCampusIds((int) Auth::id());

        $requestedCampusId = isset($validated['campus_id']) ? (int) $validated['campus_id'] : null;

        // Thrown explicitly (rather than abort_unless(..., 403)) because the
        // JSON data endpoint is read by ApiExceptionHandler, which maps
        // AccessDeniedHttpException to 403 but falls back to 500 for the
        // generic HttpException that abort() throws.
        if ($requestedCampusId !== null && ! in_array($requestedCampusId, $grantedCampusIds, true)) {
            throw new AccessDeniedHttpException('Not granted view_merchandise_report at the requested campus.');
        }

        $scopedCampusIds = $requestedCampusId !== null ? [$requestedCampusId] : $grantedCampusIds;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $status = $validated['status'] ?? null;

        return [
            'filters' => [
                'campus_id' => $requestedCampusId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $status,
            ],
            'granted_campuses' => Campus::query()->whereIn('id', $grantedCampusIds)->orderBy('name')->get(['id', 'name']),
            'statuses' => RedemptionOrder::STATUSES,
            'orders_by_status' => $this->ordersByStatusQuery->handle($scopedCampusIds, $dateFrom, $dateTo, $status),
            'gold_summary' => $this->goldSummaryQuery->handle($scopedCampusIds, $dateFrom, $dateTo),
            'most_redeemed' => $this->mostRedeemedQuery->handle($scopedCampusIds, $dateFrom, $dateTo),
            'stock' => $this->stockQuery->handle($scopedCampusIds, $dateFrom, $dateTo),
        ];
    }

    /**
     * Campuses where the caller actually holds view_merchandise_report — the
     * route `can:` gate above is session-scoped and only a coarse first
     * filter (mirrors RedemptionOrderController::grantedCampusIds).
     *
     * @return list<int>
     */
    private function grantedCampusIds(int $userId): array
    {
        return DB::table('campus_user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'campus_user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('campus_user_roles.user_id', $userId)
            ->where('permissions.code', self::REPORT_PERMISSION)
            ->distinct()
            ->pluck('campus_user_roles.campus_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
