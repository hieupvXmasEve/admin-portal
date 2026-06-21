<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Actions\Egc\SyncEgcBlockResultsAction;
use App\Modules\Finance\Queries\Egc\ListEgcBlockResultsQuery;
use App\Modules\Finance\Queries\Egc\ListEgcRetakeAdjustmentsQuery;
use App\Modules\Finance\Support\FinanceSemesterContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EgcBlockResultsController extends Controller
{
    public function index(
        Request $request,
        ListEgcBlockResultsQuery $query,
        ListEgcRetakeAdjustmentsQuery $adjustmentsQuery,
    ): Response {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'result' => 'nullable|string|in:all,pending,pass,fail',
            'per_page' => 'nullable|integer|in:20,50,100',
            'page' => 'nullable|integer|min:1',
        ]);

        $currentCampusId = session('current_campus_id') ? (int) session('current_campus_id') : null;
        $semesterId = FinanceSemesterContextResolver::selectedId();

        $blocks = $semesterId ? $query->handle($semesterId, $validated, $currentCampusId) : collect();
        $adjustments = $semesterId ? $adjustmentsQuery->handle($semesterId, $currentCampusId) : [
            'eligible_with_targets' => [],
            'eligible_no_targets' => [],
            'ineligible' => [],
            'already_discounted' => [],
        ];
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/EgcOperations/BlockResults', [
            'blocks' => $blocks,
            'adjustments' => $adjustments,
            'semesters' => $semesters,
            'currentSemester' => $semesterId ? Semester::find($semesterId) : null,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'result' => $validated['result'] ?? 'all',
                'per_page' => (int) ($validated['per_page'] ?? 50),
                'page' => (int) ($validated['page'] ?? 1),
            ],
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:semesters,id',
        ]);

        $result = SyncEgcBlockResultsAction::run((int) $validated['semester_id']);
        $reconciliation = $result['reconciliation'];
        $message = "Synced {$result['synced']} of {$result['total']} blocks. "
            ."Reconciled {$reconciliation['students_reconciled']} students "
            ."({$reconciliation['releveled_blocks']} blocks releveled, "
            ."{$reconciliation['discounts_applied']} auto discounts).";

        Inertia::flash('success', $message);
        Inertia::flash('egc_reconciliation', $reconciliation);

        return redirect()->route('finance.egc.block-results.index');
    }
}
