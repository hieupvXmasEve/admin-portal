<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Campus\ListCampusAction;
use App\Constants\CampusRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campus\ListCampusRequest;
use App\Http\Requests\Campus\StoreCampusRequest;
use App\Http\Requests\Campus\UpdateCampusRequest;
use App\Models\Campus;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CampusController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:view_campus')->only(['index', 'show']);
        // $this->middleware('can:create_campus')->only(['create', 'store']);
        // $this->middleware('can:edit_campus')->only(['edit', 'update']);
        // $this->middleware('can:delete_campus')->only(['destroy']);
    }

    /**
     * Display a listing of campuses
     */
    public function index(ListCampusRequest $request, ListCampusAction $action): Response
    {
        $validated = $request->validated();
        $campuses = $action->execute($validated)->withQueryString();

        return Inertia::render('campuses/Index', [
            'campuses' => $campuses,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
                'per_page' => $validated['per_page'] ?? null,
            ],
        ]);
    }

    /**
     * Show the form for creating a new campus
     */
    public function create(): Response
    {
        return Inertia::render('campuses/Create');
    }

    /**
     * Store a newly created campus
     */
    public function store(StoreCampusRequest $request, \App\Actions\Campus\CreateCampusAction $action): RedirectResponse
    {
        $action->execute($request->validated());

        return redirect()->route(CampusRoutes::INDEX)->with('success', 'Campus created successfully.');
    }

    /**
     * Display the specified campus with building management
     */
    public function show(\App\Http\Requests\Campus\ShowCampusRequest $request, Campus $campus, \App\Actions\Campus\GetCampusAction $action): Response
    {
        $validated = $request->validated();
        [$cachedCampus, $buildings] = $action->execute($campus, $validated);

        return Inertia::render('campuses/Show', [
            'campus' => $cachedCampus,
            'buildings' => $buildings->withQueryString(),
            'filters' => [
                'search' => $validated['search'] ?? null,
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
                'per_page' => $validated['per_page'] ?? null,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified campus
     */
    public function edit(Campus $campus): Response
    {
        return Inertia::render('campuses/Edit', [
            'campus' => $campus,
        ]);
    }

    /**
     * Update the specified campus
     */
    public function update(UpdateCampusRequest $request, Campus $campus, \App\Actions\Campus\UpdateCampusAction $action): RedirectResponse
    {
        $action->execute($campus, $request->validated());

        return redirect()->route(CampusRoutes::INDEX)->with('success', 'Campus updated successfully.');
    }

    /**
     * Remove the specified campus
     */
    public function destroy(Campus $campus, \App\Actions\Campus\DeleteCampusAction $action): RedirectResponse
    {
        $action->execute($campus);

        return redirect()->route(CampusRoutes::INDEX)->with('success', 'Campus deleted successfully.');
    }

    /**
     * Get campuses for API/dropdown usage
     */
    public function api(Request $request, \App\Actions\Campus\GetCampusDropdownAction $action)
    {
        $campuses = $action->execute($request->string('search')->toString());

        return response()->json([
            'success' => true,
            'data' => $campuses,
        ]);
    }
}
