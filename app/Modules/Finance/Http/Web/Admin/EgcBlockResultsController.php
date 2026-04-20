<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Actions\Egc\SyncEgcBlockResultsAction;
use App\Modules\Finance\Queries\Egc\ListEgcBlockResultsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EgcBlockResultsController extends Controller
{
    public function index(
        Request $request,
        ListEgcBlockResultsQuery $query
    ): Response {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'search' => 'nullable|string|max:100',
            'result' => 'nullable|string|in:pending,pass,fail',
            'per_page' => 'nullable|integer|in:20,50,100',
            'page' => 'nullable|integer|min:1',
        ]);

        $currentSemester = Semester::where('is_active', true)->first();
        $currentCampusId = session('current_campus_id') ? (int) session('current_campus_id') : null;
        $semesterId = isset($validated['semester_id'])
            ? (int) $validated['semester_id']
            : $currentSemester?->id;

        $blocks = $semesterId ? $query->handle($semesterId, $validated, $currentCampusId) : collect();
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/EgcOperations/BlockResults', [
            'blocks' => $blocks,
            'semesters' => $semesters,
            'currentSemester' => $semesterId ? Semester::find($semesterId) : $currentSemester,
            'filters' => [
                'semester_id' => $semesterId ? (string) $semesterId : null,
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

        return redirect()
            ->route('finance.egc.block-results.index', ['semester_id' => $validated['semester_id']])
            ->with('success', "Synced {$result['synced']} of {$result['total']} blocks.");
    }
}
