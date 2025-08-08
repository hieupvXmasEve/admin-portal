<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Specialization;
use Exception;
use Illuminate\Support\Facades\Log;

class SpecializationService
{
    /**
     * Create a new specialization.
     *
     * @param  array  $data  Validated data.
     */
    public function createSpecialization(array $data): Specialization
    {
        Log::info('Creating a new specialization', $data);

        return Specialization::create($data);
    }

    /**
     * Update an existing specialization.
     *
     * @param  Specialization  $specialization  The specialization to update.
     * @param  array  $data  Validated data.
     */
    public function updateSpecialization(Specialization $specialization, array $data): Specialization
    {
        Log::info("Updating specialization {$specialization->id}", $data);
        $specialization->update($data);

        return $specialization;
    }

    /**
     * Delete a specialization.
     *
     * @param  Specialization  $specialization  The specialization to delete.
     */
    public function deleteSpecialization(Specialization $specialization): void
    {
        if ($specialization->curriculumVersions()->exists()) {
            throw new Exception('Cannot delete specialization with existing curriculum versions.');
        }

        Log::warning("Deleting specialization {$specialization->id}");
        $specialization->delete();
    }

    /**
     * Bulk delete specializations.
     *
     * @param  array  $specializationIds  Array of specialization IDs to delete.
     * @return array Array containing deleted and failed specializations.
     */
    public function bulkDeleteSpecializations(array $specializationIds): array
    {
        $specializations = Specialization::whereIn('id', $specializationIds)->get();
        $deleted = [];
        $failed = [];

        foreach ($specializations as $specialization) {
            if ($specialization->curriculumVersions()->count() > 0) {
                $failed[] = [
                    'name' => $specialization->name,
                    'reason' => 'Has existing curriculum versions',
                ];
            } else {
                $deleted[] = $specialization->name;
                $specialization->delete();
            }
        }

        Log::info('Bulk delete specializations', [
            'deleted_count' => count($deleted),
            'failed_count' => count($failed),
        ]);

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }
}
