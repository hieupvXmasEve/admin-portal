<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Building\StoreBuildingRequest;
use App\Http\Requests\Building\UpdateBuildingRequest;
use App\Http\Resources\Building\BuildingResource;
use App\Models\Building;
use App\Services\BuildingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuildingController extends Controller
{
    public function __construct(protected BuildingService $buildingService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $buildings = Building::query()
            ->when($request->input('campus_id'), function ($query, $campusId) {
                $query->where('campus_id', $campusId);
            })
            ->paginate();

        return BuildingResource::collection($buildings);
    }

    public function store(StoreBuildingRequest $request): BuildingResource
    {
        $building = $this->buildingService->createBuilding($request->validated());

        return new BuildingResource($building);
    }

    public function show(Building $building): BuildingResource
    {
        return new BuildingResource($building);
    }

    public function update(UpdateBuildingRequest $request, Building $building): BuildingResource
    {
        $updatedBuilding = $this->buildingService->updateBuilding($building, $request->validated());

        return new BuildingResource($updatedBuilding);
    }

    public function destroy(Building $building): \Illuminate\Http\Response
    {
        $this->buildingService->deleteBuilding($building);

        return response()->noContent();
    }
}
