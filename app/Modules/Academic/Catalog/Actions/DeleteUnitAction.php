<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Unit;
use App\Models\UnitPrerequisiteCondition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteUnitAction
{
    /**
     * @return array{allowed: bool, reason: string|null, restrictions: list<string>}
     */
    public function eligibility(Unit $unit): array
    {
        $restrictions = [];

        if ($unit->curriculumUnits()->exists()) {
            $restrictions[] = 'Unit is part of active curricula';
        }
        if ($unit->prerequisiteGroups()->whereHas('conditions')->exists()) {
            $restrictions[] = 'Unit has prerequisite groups';
        }
        if (UnitPrerequisiteCondition::query()->where('required_unit_id', $unit->id)->exists()) {
            $restrictions[] = 'Unit is required by other units';
        }
        if ($unit->equivalentUnits()->exists() || $unit->equivalentTo()->exists()) {
            $restrictions[] = 'Unit has equivalency relationships';
        }

        return [
            'allowed' => $restrictions === [],
            'reason' => $restrictions === [] ? null : implode(', ', $restrictions),
            'restrictions' => $restrictions,
        ];
    }

    public function handle(Unit $unit): void
    {
        DB::transaction(function () use ($unit): void {
            $unit->prerequisiteGroups()->each(function ($group): void {
                $group->conditions()->delete();
                $group->delete();
            });
            $unit->equivalentUnits()->delete();
            $unit->delete();

            Log::info('Deleted Academic Catalog unit', ['unit_id' => $unit->id, 'unit_code' => $unit->code]);
        });
    }
}
