<?php

namespace App\Services;

use App\Models\Building;
use Illuminate\Support\Facades\Log;

class BuildingService
{
    /**
     * Create a new building.
     *
     * @param array $data Validated data.
     * @return Building
     */
    public function createBuilding(array $data): Building
    {
        Log::info('Creating a new building', $data);
        return Building::create($data);
    }

    /**
     * Update an existing building.
     *
     * @param Building $building The building to update.
     * @param array $data Validated data.
     * @return Building
     */
    public function updateBuilding(Building $building, array $data): Building
    {
        Log::info("Updating building {$building->id}", $data);
        $building->update($data);
        return $building;
    }

    /**
     * Delete a building.
     *
     * @param Building $building The building to delete.
     * @return void
     */
    public function deleteBuilding(Building $building): void
    {
        Log::warning("Deleting building {$building->id}");
        // Add any cleanup logic here, e.g., deleting related models.
        $building->delete();
    }
}
