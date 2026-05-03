<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Campus;
use App\Modules\Academic\Actions\CreateBuildingAction;
use App\Modules\Academic\Actions\DeleteBuildingAction;
use App\Modules\Academic\Actions\UpdateBuildingAction;
use App\Modules\Academic\Http\Requests\Building\StoreBuildingRequest;
use App\Modules\Academic\Http\Requests\Building\UpdateBuildingRequest;
use App\Modules\Academic\Queries\ListBuildingsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
    public function __construct(
        private readonly ListBuildingsQuery $query,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'sort' => 'nullable|string|in:name,code,campus_id,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        return Inertia::render('Buildings/Index', [
            'buildings' => $this->query->handle($validated),
            'filters' => $request->only(['search', 'campus_id', 'sort', 'direction', 'per_page']),
            'campuses' => $this->query->getCampusOptions(),
        ]);
    }

    public function show(Building $building): Response
    {
        $building->load(['campus']);

        $statistics = [
            'total_rooms' => $building->getTotalRooms(),
            'available_rooms' => 0,
            'occupied_rooms' => 0,
            'maintenance_rooms' => 0,
        ];

        return Inertia::render('Buildings/Show', [
            'building' => $building,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Render create building form inside a modal (route-based modal via @inertiaui/modal-vue).
     * The Campus is passed via route parameter so the form knows which campus to attach to.
     */
    public function create(Campus $campus): Response
    {
        return Inertia::render('Buildings/Create', [
            'campus' => $campus->only('id', 'name'),
        ]);
    }

    /**
     * Store a new building and redirect back to the campus detail page.
     */
    public function store(StoreBuildingRequest $request, Campus $campus): RedirectResponse
    {
        $data = array_merge($request->validated(), ['campus_id' => $campus->id]);

        CreateBuildingAction::run($data);

        Inertia::flash('message', 'Building created successfully.');

        return back();
    }

    /**
     * Render edit building form inside a modal (route-based modal via @inertiaui/modal-vue).
     */
    public function edit(Campus $campus, Building $building): Response
    {
        abort_unless($building->campus_id === $campus->id, 404);

        return Inertia::render('Buildings/Edit', [
            'campus' => $campus->only('id', 'name'),
            'building' => $building->only('id', 'name', 'code', 'description', 'address'),
        ]);
    }

    /**
     * Update building and redirect back to the campus detail page.
     */
    public function update(UpdateBuildingRequest $request, Campus $campus, Building $building): RedirectResponse
    {
        abort_unless($building->campus_id === $campus->id, 404);

        UpdateBuildingAction::run($building, $request->validated());

        Inertia::flash('message', 'Building updated successfully.');

        return back();
    }

    /**
     * Delete building and redirect back to the campus detail page.
     */
    public function destroy(Campus $campus, Building $building): RedirectResponse
    {
        abort_unless($building->campus_id === $campus->id, 404);

        DeleteBuildingAction::run($building);

        Inertia::flash('message', 'Building deleted successfully.');

        return back();
    }

    /**
     * JSON endpoint for dropdown/autocomplete usage (e.g. room assignment, schedule forms).
     */
    public function api(Request $request): JsonResponse
    {
        $request->validate([
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'search' => 'nullable|string|max:255',
        ]);

        $buildings = $this->query->getForDropdown(
            $request->integer('campus_id') ?: null,
            $request->string('search')->toString() ?: null,
        );

        return response()->json([
            'success' => true,
            'data' => $buildings,
        ]);
    }
}
