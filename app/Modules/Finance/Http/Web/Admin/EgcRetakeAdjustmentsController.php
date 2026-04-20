<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Actions\Egc\ApplyEgcMajorEntryCreditAction;
use App\Modules\Finance\Actions\Egc\ApplyEgcRetakeDiscountAction;
use App\Modules\Finance\Queries\Egc\ListEgcRetakeAdjustmentsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EgcRetakeAdjustmentsController extends Controller
{
    public function index(
        Request $request,
        ListEgcRetakeAdjustmentsQuery $query
    ): Response {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $currentSemester = Semester::where('is_active', true)->first();
        $currentCampusId = session('current_campus_id') ? (int) session('current_campus_id') : null;
        $semesterId = isset($validated['semester_id'])
            ? (int) $validated['semester_id']
            : $currentSemester?->id;

        $adjustments = $semesterId ? $query->handle($semesterId, $currentCampusId) : [
            'eligible_with_targets' => [],
            'eligible_no_targets' => [],
            'ineligible' => [],
            'already_discounted' => [],
        ];

        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/EgcOperations/RetakeAdjustments', [
            'adjustments' => $adjustments,
            'semesters' => $semesters,
            'currentSemester' => $semesterId ? Semester::find($semesterId) : $currentSemester,
            'filters' => [
                'semester_id' => $semesterId ? (string) $semesterId : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'egc_block_id' => 'required|integer|exists:egc_blocks,id',
            'target_charge_id' => 'required|integer|exists:finance_charges,id',
        ]);

        ApplyEgcRetakeDiscountAction::run(
            (int) $validated['egc_block_id'],
            (int) $validated['target_charge_id']
        );

        return redirect()
            ->back()
            ->with('success', 'Retake discount applied successfully.');
    }

    public function applyMajorEntryCredit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'semester_id' => 'required|integer|exists:semesters,id',
        ]);

        ApplyEgcMajorEntryCreditAction::run(
            (int) $validated['student_id'],
            (int) $validated['semester_id']
        );

        return redirect()
            ->back()
            ->with('success', 'Major entry credit applied successfully.');
    }
}
