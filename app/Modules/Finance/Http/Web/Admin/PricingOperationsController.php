<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Unit;
use App\Modules\Finance\Actions\Pricing\ActivatePricingRuleVersionAction;
use App\Modules\Finance\Actions\Pricing\CreatePricingRuleVersionAction;
use App\Modules\Finance\Actions\Pricing\DeactivatePricingRuleVersionAction;
use App\Modules\Finance\Http\Requests\Pricing\ListPricingRulesRequest;
use App\Modules\Finance\Http\Requests\Pricing\StorePricingRuleVersionRequest;
use App\Modules\Finance\Models\FinancePricingCatalogItem;
use App\Modules\Finance\Queries\Pricing\ListPricingCoverageWarningsQuery;
use App\Modules\Finance\Queries\Pricing\ListPricingRulesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PricingOperationsController extends Controller
{
    public function index(
        ListPricingRulesRequest $request,
        ListPricingRulesQuery $listQuery,
        ListPricingCoverageWarningsQuery $coverageQuery,
    ): Response {
        $result = $listQuery->handle(
            obligationType: $request->input('obligation_type'),
            perPage: (int) $request->input('per_page', 50),
        );

        return Inertia::render('Finance/PricingOperations/Index', [
            'rules' => $result['items'],
            'obligation_types' => $result['obligation_types'],
            'coverage_warnings' => $coverageQuery->handle(),
            'filters' => [
                'obligation_type' => $request->input('obligation_type', 'all'),
                'per_page' => (int) $request->input('per_page', 50),
            ],
        ]);
    }

    public function searchUnits(Request $request): JsonResponse
    {
        $query = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $units = Unit::query()
            ->where(function ($builder) use ($query): void {
                $builder->where('code', 'like', "%{$query['q']}%")
                    ->orWhere('name', 'like', "%{$query['q']}%");
            })
            ->limit($query['limit'] ?? 10)
            ->get(['id', 'code', 'name', 'credit_points']);

        return ApiResponse::success($units, message: 'Units retrieved successfully');
    }

    public function store(
        StorePricingRuleVersionRequest $request,
        CreatePricingRuleVersionAction $action,
    ): RedirectResponse {
        $item = $action->handle($request->pricingPayload());

        Inertia::flash('success', "Pricing rule version [{$item->rule_version}] created.");

        return redirect()->route('finance.pricing-operations.index', [
            'obligation_type' => $item->obligation_type,
        ]);
    }

    public function activate(
        FinancePricingCatalogItem $pricingRule,
        ActivatePricingRuleVersionAction $action,
    ): RedirectResponse {
        $this->authorizeManage();

        $item = $action->handle($pricingRule);

        Inertia::flash('success', "Pricing rule [{$item->rule_version}] activated.");

        return redirect()->back();
    }

    public function deactivate(
        FinancePricingCatalogItem $pricingRule,
        DeactivatePricingRuleVersionAction $action,
    ): RedirectResponse {
        $this->authorizeManage();

        $item = $action->handle($pricingRule);

        Inertia::flash('success', "Pricing rule [{$item->rule_version}] deactivated.");

        return redirect()->back();
    }

    private function authorizeManage(): void
    {
        abort_unless(
            auth()->user()?->can('manage_finance_pricing_operations') ?? false,
            403,
        );
    }
}
