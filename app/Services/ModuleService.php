<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\DB;

class ModuleService
{
    public function create(array $data): Module
    {
        return DB::transaction(function () use ($data) {
            $unitsData = $data['units'] ?? [];
            unset($data['units']);

            $module = Module::create($data);

            if (! empty($unitsData)) {
                $this->syncUnits($module, $unitsData);
            }

            $this->updateTotalCredits($module);

            return $module->load('units', 'campus');
        });
    }

    public function update(Module $module, array $data): Module
    {
        return DB::transaction(function () use ($module, $data) {
            $unitsData = $data['units'] ?? null;
            unset($data['units']);

            $module->update($data);

            if ($unitsData !== null) {
                $this->syncUnits($module, $unitsData);
                $this->updateTotalCredits($module);
            }

            return $module->fresh(['units', 'campus']);
        });
    }

    public function delete(Module $module): bool
    {
        return DB::transaction(function () use ($module) {
            $module->units()->detach();
            return $module->delete();
        });
    }

    public function syncUnits(Module $module, array $unitsData): void
    {
        $syncData = [];

        foreach ($unitsData as $unitData) {
            $unitId = $unitData['unit_id'] ?? $unitData['id'] ?? null;

            if ($unitId) {
                $syncData[$unitId] = [
                    'grading_type' => $unitData['grading_type'] ?? 'grade',
                    'weight' => $unitData['weight'] ?? null,
                    'order' => $unitData['order'] ?? 0,
                ];
            }
        }

        $module->units()->sync($syncData);

        // Auto-calculate and update total_credits
        $this->updateTotalCredits($module);
    }

    public function attachUnits(Module $module, array $unitIds): void
    {
        foreach ($unitIds as $index => $unitId) {
            if (! $module->units()->where('unit_id', $unitId)->exists()) {
                $module->units()->attach($unitId, [
                    'order' => $index,
                ]);
            }
        }

        $this->updateTotalCredits($module);
    }

    public function detachUnits(Module $module, array $unitIds): void
    {
        $module->units()->detach($unitIds);
        $this->updateTotalCredits($module);
    }

    public function updateTotalCredits(Module $module): void
    {
        $totalCredits = $module->calculateTotalCredits();
        $module->update(['total_credits' => $totalCredits]);
    }
}
