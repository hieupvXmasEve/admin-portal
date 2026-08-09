<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Facilities\Actions\CreateBuildingAction;
use App\Modules\Facilities\Actions\DeleteBuildingAction;
use App\Modules\Facilities\Actions\UpdateBuildingAction;
use App\Modules\Facilities\Http\Requests\Building\StoreBuildingRequest;
use App\Modules\Facilities\Http\Requests\Building\UpdateBuildingRequest;
use App\Modules\Facilities\Models\Building;
use App\Modules\Facilities\Queries\ListBuildingsQuery;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
    public function __construct(
        private readonly ListBuildingsQuery $query,
        private readonly CampusReferenceReader $campusReferences,
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

        return Inertia::render('Buildings/Show', [
            'building' => $building,
            'statistics' => [
                'total_rooms' => $building->getTotalRooms(),
                'available_rooms' => 0,
                'occupied_rooms' => 0,
                'maintenance_rooms' => 0,
            ],
        ]);
    }

    public function create(string $campus): Response
    {
        $campusReference = $this->campusReference((int) $campus);

        return Inertia::render('Buildings/Create', [
            'campus' => $campusReference,
        ]);
    }

    public function store(StoreBuildingRequest $request, string $campus): RedirectResponse
    {
        CreateBuildingAction::run([...$request->validated(), 'campus_id' => $this->campusReference((int) $campus)['id']]);

        Inertia::flash('message', 'Building created successfully.');

        return back();
    }

    public function edit(string $campus, Building $building): Response
    {
        $campusReference = $this->campusReference((int) $campus);
        abort_unless($building->campus_id === $campusReference['id'], 404);

        return Inertia::render('Buildings/Edit', [
            'campus' => $campusReference,
            'building' => $building->only('id', 'name', 'code', 'description', 'address'),
        ]);
    }

    public function update(UpdateBuildingRequest $request, string $campus, Building $building): RedirectResponse
    {
        abort_unless($building->campus_id === $this->campusReference((int) $campus)['id'], 404);

        UpdateBuildingAction::run($building, $request->validated());

        Inertia::flash('message', 'Building updated successfully.');

        return back();
    }

    public function destroy(string $campus, Building $building): RedirectResponse
    {
        abort_unless($building->campus_id === $this->campusReference((int) $campus)['id'], 404);

        DeleteBuildingAction::run($building);

        Inertia::flash('message', 'Building deleted successfully.');

        return back();
    }

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

    /** @return array{id: int, name: string} */
    private function campusReference(int $campusId): array
    {
        $campus = collect($this->campusReferences->all())
            ->first(fn ($reference) => $reference->id === $campusId);

        abort_if($campus === null, 404);

        return ['id' => $campus->id, 'name' => $campus->name];
    }
}
