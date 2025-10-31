<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'module' => [
                'id' => $this->resource['module']->id,
                'code' => $this->resource['module']->code,
                'name' => $this->resource['module']->name,
                'description' => $this->resource['module']->description,
                'total_credits' => (float) $this->resource['module']->total_credits,
                'grading_type' => $this->resource['module']->grading_type,
            ],
            'status' => $this->resource['status'],
            'grade' => $this->resource['grade'],
            'completion' => $this->resource['completion'],
            'completed_count' => $this->resource['completed_count'],
            'total_count' => $this->resource['total_count'],
            'sub_units' => SubUnitProgressResource::collection($this->resource['sub_units']),
        ];
    }
}
