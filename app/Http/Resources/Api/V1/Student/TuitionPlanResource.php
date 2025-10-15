<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TuitionPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'curriculum_version' => $this->whenLoaded('curriculumVersion', function () {
                return [
                    'id' => $this->curriculumVersion->id,
                    'version_code' => $this->curriculumVersion->version_code,
                    'program' => $this->whenLoaded('curriculumVersion.program', function () {
                        return [
                            'id' => $this->curriculumVersion->program->id,
                            'name' => $this->curriculumVersion->program->name,
                            'code' => $this->curriculumVersion->program->code,
                        ];
                    }),
                    'specialization' => $this->when(
                        $this->curriculumVersion->relationLoaded('specialization') && $this->curriculumVersion->specialization,
                        function () {
                            return [
                                'id' => $this->curriculumVersion->specialization->id,
                                'name' => $this->curriculumVersion->specialization->name,
                                'code' => $this->curriculumVersion->specialization->code,
                            ];
                        }
                    ),
                ];
            }),
            'intake_semester' => $this->whenLoaded('intakeSemester', function () {
                return [
                    'id' => $this->intakeSemester->id,
                    'code' => $this->intakeSemester->code,
                    'name' => $this->intakeSemester->name,
                    'start_date' => $this->intakeSemester->start_date?->format('Y-m-d'),
                    'end_date' => $this->intakeSemester->end_date?->format('Y-m-d'),
                ];
            }),
            'terms' => $this->whenLoaded('terms', function () {
                return $this->terms->map(function ($term) {
                    $termData = [
                        'id' => $term->id,
                        'term_number' => $term->term_number,
                        'amount' => (float) $term->amount,
                        'due_date' => $term->due_date?->format('Y-m-d'),
                        'formatted_due_date' => $term->due_date?->format('d/m/Y'),
                    ];

                    // Check if semester relationship is loaded
                    if ($term->relationLoaded('semester') && $term->semester) {
                        $termData['semester'] = [
                            'id' => $term->semester->id,
                            'code' => $term->semester->code,
                            'name' => $term->semester->name,
                            'start_date' => $term->semester->start_date?->format('Y-m-d'),
                            'end_date' => $term->semester->end_date?->format('Y-m-d'),
                        ];
                    }

                    return $termData;
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
