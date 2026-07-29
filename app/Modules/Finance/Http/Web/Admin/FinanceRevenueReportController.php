<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\Reporting\GetRevenueReportRequest;
use App\Modules\Finance\Queries\Reporting\GetRevenueByPeriodQuery;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Always school-wide (every campus). Deliberately does not call
 * FinanceSemesterContextResolver::selectedId() or read app('campus') — this
 * report compares multiple semesters side by side, so the global Finance
 * semester context does not apply here.
 */
class FinanceRevenueReportController extends Controller
{
    public function index(GetRevenueReportRequest $request, GetRevenueByPeriodQuery $query): Response
    {
        $filters = array_replace([
            'semester_ids' => [],
            'campus_id' => null,
            'fee_type' => null,
        ], $request->validated());

        $result = $query->handle($filters);

        return Inertia::render('Finance/Revenue/Index', [
            'rows' => $result['rows'],
            'totals' => $result['totals'],
            'breakdowns' => $result['breakdowns'],
            'unattributed' => $result['unattributed'],
            'filters' => $filters,
            'filter_options' => $query->filterOptions(),
            'computed_at' => now()->toIso8601String(),
        ]);
    }
}
