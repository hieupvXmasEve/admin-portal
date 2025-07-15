<?php

declare(strict_types=1);

namespace App\Http\Resources\Syllabus;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyllabusResource extends JsonResource
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
            'curriculum_unit_id' => $this->curriculum_unit_id,
            'version' => $this->version,
            'description' => $this->description,
            'total_hours' => $this->total_hours,
            'hours_per_session' => $this->hours_per_session,
            'is_active' => $this->is_active,
            'total_assessment_weight' => $this->total_assessment_weight ?? 0,
            'has_complete_assessment_structure' => $this->total_assessment_weight === 100,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'curriculum_unit' => $this->whenLoaded('curriculumUnit', function () {
                return [
                    'id' => $this->curriculumUnit->id,
                    'type' => $this->curriculumUnit->type,
                    'year_level' => $this->curriculumUnit->year_level,
                    'semester_number' => $this->curriculumUnit->semester_number,
                    'semester' => $this->whenLoaded('curriculumUnit.semester', function () {
                        return [
                            'id' => $this->curriculumUnit->semester->id,
                            'name' => $this->curriculumUnit->semester->name,
                            'code' => $this->curriculumUnit->semester->code,
                        ];
                    }),
                    'curriculum_version' => $this->whenLoaded('curriculumUnit.curriculumVersion', function () {
                        return [
                            'id' => $this->curriculumUnit->curriculumVersion->id,
                            'version_code' => $this->curriculumUnit->curriculumVersion->version_code,
                            'program' => $this->whenLoaded('curriculumUnit.curriculumVersion.program', function () {
                                return [
                                    'id' => $this->curriculumUnit->curriculumVersion->program->id,
                                    'name' => $this->curriculumUnit->curriculumVersion->program->name,
                                    'degree_level' => $this->curriculumUnit->curriculumVersion->program->degree_level ?? null,
                                ];
                            }),
                            'specialization' => $this->whenLoaded('curriculumUnit.curriculumVersion.specialization', function () {
                                return [
                                    'id' => $this->curriculumUnit->curriculumVersion->specialization->id,
                                    'name' => $this->curriculumUnit->curriculumVersion->specialization->name,
                                    'description' => $this->curriculumUnit->curriculumVersion->specialization->description ?? null,
                                ];
                            }),
                        ];
                    }),
                ];
            }),

            'assessment_components' => $this->whenLoaded('assessmentComponents', function () {
                return $this->assessmentComponents->map(function ($component) {
                    return [
                        'id' => $component->id,
                        'name' => $component->name,
                        'weight' => $component->weight,
                        'type' => $component->type,
                        'is_required_to_sit_final_exam' => $component->is_required_to_sit_final_exam,
                        'details' => $this->whenLoaded('assessmentComponents.details', function () use ($component) {
                            return $component->details->map(function ($detail) {
                                return [
                                    'id' => $detail->id,
                                    'name' => $detail->name,
                                    'weight' => $detail->weight,
                                ];
                            });
                        }),
                    ];
                });
            }),
        ];
    }
}
