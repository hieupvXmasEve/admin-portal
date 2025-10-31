<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campus_id' => $this->campus_id,
            'campus' => $this->whenLoaded('campus', fn () => [
                'id' => $this->campus->id,
                'name' => $this->campus->name,
                'code' => $this->campus->code,
            ]),
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'grading_type' => $this->grading_type,
            'total_credits' => (float) $this->total_credits,
            'prerequisite_module_id' => $this->prerequisite_module_id,
            'prerequisite_module' => $this->whenLoaded('prerequisiteModule', fn () => [
                'id' => $this->prerequisiteModule->id,
                'code' => $this->prerequisiteModule->code,
                'name' => $this->prerequisiteModule->name,
            ]),
            'units' => $this->whenLoaded('units', function () {
                return $this->units->map(fn ($unit) => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_points' => (float) $unit->credit_points,
                    'weight' => $unit->pivot->weight ? (float) $unit->pivot->weight : null,
                    'order' => $unit->pivot->order,
                ]);
            }),
            'units_count' => $this->whenCounted('units'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
