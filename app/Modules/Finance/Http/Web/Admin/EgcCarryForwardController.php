<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Actions\Egc\ApplyEgcCarryForwardAction;
use App\Modules\Finance\Queries\Egc\ListEgcCarryForwardCandidatesQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EgcCarryForwardController extends Controller
{
    public function index(Request $request, ListEgcCarryForwardCandidatesQuery $query): Response
    {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $currentSemester = Semester::where('is_active', true)->first();
        $currentCampusId = session('current_campus_id') ? (int) session('current_campus_id') : null;
        $semesterId = isset($validated['semester_id'])
            ? (int) $validated['semester_id']
            : $currentSemester?->id;

        $candidates = $semesterId ? $query->handle($semesterId, $currentCampusId) : [
            'eligible' => [],
            'needs_data_repair' => [],
            'ineligible' => [],
        ];

        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/EgcOperations/CarryForward', [
            'candidates' => $candidates,
            'semesters' => $semesters,
            'currentSemester' => $semesterId ? Semester::find($semesterId) : $currentSemester,
            'filters' => [
                'semester_id' => $semesterId ? (string) $semesterId : null,
            ],
        ]);
    }

    public function store(Request $request, ApplyEgcCarryForwardAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'semester_id' => 'required|integer|exists:semesters,id',
        ]);

        $result = $action->run(
            (int) $validated['student_id'],
            (int) $validated['semester_id'],
            auth()->id(),
            session('current_campus_id') ? (int) session('current_campus_id') : null,
        );

        return redirect()
            ->back()
            ->with('success', 'Released '.number_format((float) $result['released_amount']).'đ to unapplied balance.');
    }
}
