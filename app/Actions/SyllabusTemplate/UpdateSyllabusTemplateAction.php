<?php

declare(strict_types=1);

namespace App\Actions\SyllabusTemplate;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\SyllabusTemplate;
use Illuminate\Support\Facades\DB;

class UpdateSyllabusTemplateAction
{
    /**
     * Update an existing syllabus template and its assessment components.
     */
    public function execute(SyllabusTemplate $syllabusTemplate, array $data): SyllabusTemplate
    {
        return DB::transaction(function () use ($syllabusTemplate, $data) {
            // Update template basic fields
            $syllabusTemplate->fill($data);
            $syllabusTemplate->save();

            // Sync assessment components if provided
            if (isset($data['assessment_components']) && is_array($data['assessment_components'])) {
                $incomingComponents = $data['assessment_components'];

                // Map existing components by id
                $existingComponents = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->getKey())
                    ->get()
                    ->keyBy('id');

                $keepComponentIds = [];

                foreach ($incomingComponents as $idx => $compData) {
                    if (! is_array($compData)) {
                        continue;
                    }

                    $componentId = $compData['id'] ?? null;
                    $component = null;

                    if ($componentId && isset($existingComponents[$componentId])) {
                        $component = $existingComponents[$componentId];
                        $component->fill([
                            'name' => $compData['name'] ?? $component->name,
                            'code' => $compData['code'] ?? $component->code,
                            'weight' => $compData['weight'] ?? $component->weight,
                            'type' => $compData['type'] ?? $component->type,
                            'sort_order' => $idx,
                        ]);
                        $component->save();
                    } else {
                        $component = new AssessmentComponent([
                            'name' => $compData['name'] ?? null,
                            'code' => $compData['code'] ?? null,
                            'weight' => $compData['weight'] ?? 0,
                            'type' => $compData['type'] ?? 'other',
                            'sort_order' => $idx,
                        ]);
                        $component->syllabus_template_id = $syllabusTemplate->getKey();
                        $component->save();
                    }

                    $keepComponentIds[] = $component->getKey();

                    // Sync details for this component
                    $incomingDetails = $compData['details'] ?? [];
                    $existingDetails = AssessmentComponentDetail::where('assessment_component_id', $component->getKey())
                        ->get()
                        ->keyBy('id');
                    $keepDetailIds = [];

                    if (is_array($incomingDetails) && ! empty($incomingDetails)) {
                        foreach ($incomingDetails as $d) {
                            if (! is_array($d)) {
                                continue;
                            }
                            $detailId = $d['id'] ?? null;
                            if ($detailId && isset($existingDetails[$detailId])) {
                                $detail = $existingDetails[$detailId];
                                $detail->fill([
                                    'name' => $d['name'] ?? $detail->name,
                                    'weight' => $d['weight'] ?? $detail->weight,
                                ]);
                                $detail->save();
                            } else {
                                $detail = new AssessmentComponentDetail([
                                    'assessment_component_id' => $component->getKey(),
                                    'name' => $d['name'] ?? '',
                                    'weight' => $d['weight'] ?? null,
                                ]);
                                $detail->save();
                            }
                            $keepDetailIds[] = $detail->getKey();
                        }
                    } else {
                        // If this is a new component (no componentId) and no details provided,
                        // create a default detail
                        if (! $componentId) {
                            $detail = new AssessmentComponentDetail([
                                'assessment_component_id' => $component->getKey(),
                                'name' => $component->name,
                                'weight' => 100.00,
                                'max_points' => 100.00,
                            ]);
                            $detail->save();
                            $keepDetailIds[] = $detail->getKey();
                        }
                    }

                    // Delete details not present
                    if (! empty($keepDetailIds)) {
                        AssessmentComponentDetail::where('assessment_component_id', $component->getKey())
                            ->whereNotIn('id', $keepDetailIds)
                            ->delete();
                    } else {
                        // If no details in payload, remove all existing details and create default
                        AssessmentComponentDetail::where('assessment_component_id', $component->getKey())->delete();

                        // Create default detail
                        AssessmentComponentDetail::create([
                            'assessment_component_id' => $component->getKey(),
                            'name' => $component->name,
                            'weight' => 100.00,
                            'max_points' => 100.00,
                        ]);
                    }
                }

                // Delete components not present
                if (! empty($keepComponentIds)) {
                    $toDelete = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->getKey())
                        ->whereNotIn('id', $keepComponentIds)
                        ->get();
                    foreach ($toDelete as $delComp) {
                        AssessmentComponentDetail::where('assessment_component_id', $delComp->getKey())->delete();
                        $delComp->delete();
                    }
                } else {
                    // No components in payload => remove all
                    $all = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->getKey())->get();
                    foreach ($all as $delComp) {
                        AssessmentComponentDetail::where('assessment_component_id', $delComp->getKey())->delete();
                        $delComp->delete();
                    }
                }
            }

            if (array_key_exists('is_default', $data) && $syllabusTemplate->is_default) {
                $this->setDefaultForUnit($syllabusTemplate);
            }

            return $syllabusTemplate->fresh(['unit', 'assessmentComponents.details']);
        });
    }

    private function setDefaultForUnit(SyllabusTemplate $template): void
    {
        SyllabusTemplate::where('unit_id', $template->unit_id)
            ->where('id', '!=', $template->getKey())
            ->update(['is_default' => false]);

        $template->is_default = true;
        $template->save();
    }
}
