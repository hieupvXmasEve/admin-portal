<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Actions\Egc\GenerateEgcChargesAction;
use App\Modules\Finance\Queries\Egc\PreviewEgcChargeGenerationQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EgcChargeGenerationController extends Controller
{
    public function index(
        Request $request,
        PreviewEgcChargeGenerationQuery $previewQuery
    ): Response {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $currentSemester = Semester::where('is_active', true)->first();
        $semesterId = isset($validated['semester_id'])
            ? (int) $validated['semester_id']
            : $currentSemester?->id;

        $preview = $semesterId ? $previewQuery->handle($semesterId) : [
            'eligible_students' => [],
            'ineligible_students' => [],
            'warning_students' => [],
            'summary' => ['eligible_count' => 0, 'ineligible_count' => 0, 'warning_count' => 0, 'total_count' => 0],
        ];
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/EgcOperations/GenerateCharges', [
            'preview' => $preview,
            'semesters' => $semesters,
            'currentSemester' => $semesterId ? Semester::find($semesterId) : $currentSemester,
            'filters' => ['semester_id' => $semesterId ? (string) $semesterId : null],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:semesters,id',
            'students' => 'required|array|min:1',
            'students.*.student_id' => 'required|integer|exists:students,id',
            'students.*.block_count' => 'required|integer|in:1,2',
            'students.*.current_level' => 'required|integer|min:1',
        ]);

        $results = GenerateEgcChargesAction::run($validated);

        return redirect()
            ->route('finance.egc.charges.index', ['semester_id' => $validated['semester_id']])
            ->with('success', "Generated charges: {$results['created']} created, {$results['skipped']} skipped.");
    }
}
