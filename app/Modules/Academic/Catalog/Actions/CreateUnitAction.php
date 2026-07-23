<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Unit;
use App\Services\PrerequisiteLogicService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateUnitAction
{
    public function __construct(private readonly PrerequisiteLogicService $prerequisiteLogic) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Unit
    {
        return DB::transaction(function () use ($data): Unit {
            $unit = Unit::query()->create($this->unitAttributes($data));
            $this->syncRelationships($unit, $data);

            Log::info('Created Academic Catalog unit', ['unit_id' => $unit->id, 'unit_code' => $unit->code]);

            return $unit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function unitAttributes(array $data): array
    {
        return collect($data)->only([
            'code', 'name', 'credit_points', 'level', 'base_fee', 'retake_fee', 'unit_type',
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelationships(Unit $unit, array $data): void
    {
        if (array_key_exists('prerequisite_groups', $data)) {
            foreach ($data['prerequisite_groups'] ?? [] as $groupData) {
                $group = $unit->prerequisiteGroups()->create([
                    'logic_operator' => $groupData['logic_operator'] ?? 'AND',
                    'description' => $groupData['description'] ?? null,
                ]);
                $group->conditions()->createMany($groupData['conditions'] ?? []);
            }
        } elseif (! empty($data['prerequisite_expression'])) {
            $result = $this->prerequisiteLogic->parseAndStorePrerequisiteExpression(
                $unit->id,
                $data['prerequisite_expression'],
                $data['prerequisite_description'] ?? null,
            );

            if (! $result['success']) {
                throw new \RuntimeException($result['message']);
            }
        }

        if (array_key_exists('equivalent_units', $data)) {
            $unit->equivalentUnits()->createMany($data['equivalent_units'] ?? []);
        }
    }
}
