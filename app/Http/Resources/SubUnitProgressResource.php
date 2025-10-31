<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubUnitProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unit = $this->resource['unit'];
        $academicRecord = $this->resource['academic_record'];

        return [
            'unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'credit_points' => (float) $unit->credit_points,
                'grading_type' => $unit->pivot->grading_type ?? 'grade', // From module_units pivot
            ],
            'grading_type' => $this->resource['grading_type'], // Deprecated, use unit.grading_type
            'grade' => $this->resource['grade'],
            'letter_grade' => $this->resource['letter_grade'],
            'status' => $this->resource['status'],
            'academic_record_id' => $academicRecord?->id,
        ];
    }
}
