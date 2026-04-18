<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use App\Modules\Finance\Queries\Operations\ListSettlementWorklistQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BatchDngPageController extends Controller
{
    public function __construct(
        private ListSettlementWorklistQuery $settlementQuery,
    ) {}

    public function show(Request $request): Response
    {
        // Force no_cash filter: batch DNG targets students without unapplied cash
        $request->merge(['readiness' => 'no_cash', 'per_page' => 50]);

        $data = $this->settlementQuery->handle($request);

        return Inertia::render('Finance/Operations/BatchDng', [
            'students' => $data['students'],
            'summary' => $data['summary'],
            'filters' => $data['filters'],
            'semesters' => Semester::query()
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'code'])
                ->map(fn (Semester $semester) => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                ])
                ->all(),
            'feeTypes' => DngFeeTypeOptions::all(),
        ]);
    }
}
