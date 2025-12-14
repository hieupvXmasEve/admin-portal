<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Web;

use App\Actions\Building\CreateBuildingAction;
use App\Actions\Building\DeleteBuildingAction;
use App\Actions\Building\UpdateBuildingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Building\StoreBuildingRequest;
use App\Http\Requests\Building\UpdateBuildingRequest;
use App\Models\Building;
use App\Models\Campus;
use Illuminate\Http\JsonResponse;

class BuildingController extends Controller
{
    /**
     * Store a newly created building for a specific campus.
     */
    public function store(StoreBuildingRequest $request, Campus $campus, CreateBuildingAction $action): JsonResponse
    {
        $validatedData = $request->validated();
        $validatedData['campus_id'] = $campus->id;
        
        $building = $action->execute($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Building created successfully.',
            'data' => $building,
        ]);
    }

    /**
     * Update the specified building.
     */
    public function update(UpdateBuildingRequest $request, Campus $campus, Building $building, UpdateBuildingAction $action): JsonResponse
    {
        if ($building->campus_id !== $campus->id) {
            return response()->json(['message' => 'Building does not belong to this campus.'], 404);
        }

        $updatedBuilding = $action->execute($building, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Building updated successfully.',
            'data' => $updatedBuilding,
        ]);
    }

    /**
     * Remove the specified building.
     */
    public function destroy(Campus $campus, Building $building, DeleteBuildingAction $action): JsonResponse
    {
        if ($building->campus_id !== $campus->id) {
            return response()->json(['message' => 'Building does not belong to this campus.'], 404);
        }

        $action->execute($building);

        return response()->json([
            'success' => true,
            'message' => 'Building deleted successfully.',
        ]);
    }
}
