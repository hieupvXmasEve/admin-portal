<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComponentWeightValidationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'assessment_id' => $this->resource['assessment_id'],
            'assessment_name' => $this->resource['assessment_name'],
            'current_weight' => $this->resource['current_weight'],
            'detail_weights_sum' => $this->resource['detail_weights_sum'],
            'is_valid' => $this->resource['is_valid'],
            'errors' => $this->resource['errors'] ?? [],
        ];
    }
}