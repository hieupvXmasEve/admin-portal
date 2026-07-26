<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Constants\ProgramRoutes;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Modules\Academic\Catalog\Actions\CreateProgramAction;
use App\Modules\Academic\Catalog\Actions\DeleteProgramAction;
use App\Modules\Academic\Catalog\Actions\UpdateProgramAction;
use App\Modules\Academic\Catalog\Http\Requests\ListProgramsRequest;
use App\Modules\Academic\Catalog\Http\Requests\StoreProgramRequest;
use App\Modules\Academic\Catalog\Http\Requests\UpdateProgramRequest;
use App\Modules\Academic\Catalog\Queries\GetProgramDetailQuery;
use App\Modules\Academic\Catalog\Queries\ListProgramsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProgramController extends Controller
{
    public function __construct(
        private readonly GetProgramDetailQuery $programDetails,
        private readonly ListProgramsQuery $listPrograms,
    ) {}

    public function index(ListProgramsRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('Programs/Index', [
            'programs' => $this->listPrograms->handle($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'sort' => $filters['sort'] ?? '',
                'direction' => $filters['direction'] ?? 'asc',
                'per_page' => $filters['per_page'] ?? 15,
            ],
        ]);
    }

    public function store(StoreProgramRequest $request): RedirectResponse
    {
        try {
            CreateProgramAction::run($request->validated());
        } catch (Throwable $exception) {
            Log::error('Academic Catalog program creation failed', ['exception' => $exception]);

            return back()->withInput()->withErrors(['error' => 'Failed to create program. Please try again.']);
        }

        Inertia::flash('success', 'Program created successfully.');

        return redirect()->route(ProgramRoutes::INDEX);
    }

    public function show(Program $program): Response
    {
        $this->authorize('view', $program);

        return Inertia::render('Programs/Show', $this->programDetails->handle($program));
    }

    public function update(UpdateProgramRequest $request, Program $program): RedirectResponse
    {
        $this->authorize('update', $program);

        try {
            UpdateProgramAction::run($program, $request->validated());
        } catch (Throwable $exception) {
            Log::error('Academic Catalog program update failed', ['exception' => $exception]);

            return back()->withInput()->withErrors(['error' => 'Failed to update program. Please try again.']);
        }

        Inertia::flash('success', 'Program updated successfully.');

        return redirect()->route(ProgramRoutes::INDEX);
    }

    public function destroy(Program $program): RedirectResponse
    {
        $this->authorize('delete', $program);

        try {
            DeleteProgramAction::run($program);
        } catch (Throwable $exception) {
            Log::error('Academic Catalog program deletion failed', ['exception' => $exception]);

            return back()->withErrors(['error' => $exception->getMessage()]);
        }

        Inertia::flash('success', 'Program deleted successfully.');

        return redirect()->route(ProgramRoutes::INDEX);
    }
}
