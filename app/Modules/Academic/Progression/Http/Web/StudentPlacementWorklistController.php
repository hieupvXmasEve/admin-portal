<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Modules\Academic\Progression\Queries\ListUnclassifiedStudentsQuery;
use App\Modules\Academic\Progression\Support\LifecycleFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Academic-department worklist of approved-but-unclassified students.
 * Classification itself posts to the existing students.placement.initialize
 * route; this page only lists and provides the dialog options.
 */
class StudentPlacementWorklistController extends Controller
{
    public function __construct(
        private readonly ListUnclassifiedStudentsQuery $unclassifiedStudents,
        private readonly LifecycleFormOptions $formOptions,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $campusId = session('current_campus_id');
        if (! $campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Academic/PlacementWorklist/Index', [
            'students' => $this->unclassifiedStudents->handle($validated, (int) $campusId),
            'filters' => [
                'search' => $validated['search'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
                'per_page' => (int) ($validated['per_page'] ?? 15),
            ],
            'programs' => Program::query()
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get(),
            'placementOptions' => $this->formOptions->placementOptions(),
        ]);
    }
}
