<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Modules\Academic\Actions\CreateCampusAction;
use App\Modules\Academic\Actions\UpdateCampusAction;
use App\Modules\Academic\Http\Requests\Campus\ListCampusRequest;
use App\Modules\Academic\Http\Requests\Campus\ShowCampusRequest;
use App\Modules\Academic\Http\Requests\Campus\StoreCampusRequest;
use App\Modules\Academic\Http\Requests\Campus\UpdateCampusRequest;
use App\Modules\Academic\Queries\GetCampusDetailQuery;
use App\Modules\Academic\Queries\ListCampusesQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CampusController extends Controller
{
    public function __construct(
        private readonly ListCampusesQuery $listQuery,
        private readonly GetCampusDetailQuery $detailQuery,
    ) {}

    public function index(ListCampusRequest $request): Response
    {
        $validated = $request->validated();

        return Inertia::render('Campuses/Index', [
            'campuses' => $this->listQuery->handle($validated),
            'filters' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Render create campus form inside a modal (route-based modal via @inertiaui/modal-vue).
     */
    public function create(): Response
    {
        return Inertia::render('Campuses/Create');
    }

    /**
     * Store a new campus and redirect back (modal closes on success).
     */
    public function store(StoreCampusRequest $request): RedirectResponse
    {
        CreateCampusAction::run($request->validated());

        Inertia::flash('message', 'Campus created successfully.');

        return back();
    }

    public function show(ShowCampusRequest $request, Campus $campus): Response
    {
        $result = $this->detailQuery->handle($campus, $request->validated());

        return Inertia::render('Campuses/Show', [
            'campus' => $result['campus'],
            'buildings' => $result['buildings'],
            'filters' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Render edit campus form inside a modal (route-based modal via @inertiaui/modal-vue).
     */
    public function edit(Campus $campus): Response
    {
        return Inertia::render('Campuses/Edit', [
            'campus' => $campus->only('id', 'name', 'code', 'dng_code', 'address'),
        ]);
    }

    /**
     * Update campus and redirect back (modal closes on success).
     */
    public function update(UpdateCampusRequest $request, Campus $campus): RedirectResponse
    {
        UpdateCampusAction::run($campus, $request->validated());

        Inertia::flash('message', 'Campus updated successfully.');

        return back();
    }
}
