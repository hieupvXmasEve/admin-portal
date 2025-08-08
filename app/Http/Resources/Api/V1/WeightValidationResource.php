<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeightValidationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_weight' => $this->resource['total_weight'],
            'is_valid' => $this->resource['is_valid'],
            'exceeds_limit' => $this->resource['exceeds_limit'],
            'missing_weight' => $this->resource['missing_weight'],
            'component_validations' => ComponentWeightValidationResource::collection(
                $this->resource['component_validations'] ?? []
            ),
            'errors' => $this->resource['errors'] ?? [],
        ];
    }
}
