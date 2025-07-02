<?php

namespace App\Services;

use App\Models\Campus;
use Illuminate\Support\Facades\Log;

class CampusService
{
    /**
     * Create a new campus.
     *
     * @param array $data Validated data.
     * @return Campus
     */
    public function createCampus(array $data): Campus
    {
        // Business logic for creating a campus can be added here.
        // For example, logging, firing events, etc.
        Log::info('Creating a new campus', $data);

        return Campus::create($data);
    }

    /**
     * Update an existing campus.
     *
     * @param Campus $campus The campus to update.
     * @param array $data Validated data.
     * @return Campus
     */
    public function updateCampus(Campus $campus, array $data): Campus
    {
        Log::info("Updating campus {$campus->id}", $data);
        $campus->update($data);
        return $campus;
    }

    /**
     * Delete a campus.
     *
     * @param Campus $campus The campus to delete.
     * @return void
     */
    public function deleteCampus(Campus $campus): void
    {
        Log::warning("Deleting campus {$campus->id}");
        // Add any cleanup logic here, e.g., deleting related models.
        $campus->delete();
    }
}
