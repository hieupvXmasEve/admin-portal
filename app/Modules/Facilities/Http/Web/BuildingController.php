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
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
    public function __construct(
        private readonly CampusReferenceReader $campusReferences,
    ) {}

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

    /** @return array{id: int, name: string} */
    private function campusReference(int $campusId): array
    {
        $campus = collect($this->campusReferences->all())
            ->first(fn ($reference) => $reference->id === $campusId);

        abort_if($campus === null, 404);

        return ['id' => $campus->id, 'name' => $campus->name];
    }
}
