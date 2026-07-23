<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\SyllabusTemplate;
use Illuminate\Support\Facades\DB;

class CreateSyllabusTemplateAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): SyllabusTemplate
    {
        return DB::transaction(function () use ($data): SyllabusTemplate {
            $template = SyllabusTemplate::query()->create($data);

            foreach ($data['assessment_components'] ?? [] as $index => $componentData) {
                if (! is_array($componentData)) {
                    continue;
                }

                $component = AssessmentComponent::query()->create([
                    'syllabus_template_id' => $template->getKey(),
                    'name' => $componentData['name'] ?? null,
                    'code' => $componentData['code'] ?? null,
                    'weight' => $componentData['weight'] ?? 0,
                    'type' => $componentData['type'] ?? 'other',
                    'sort_order' => $index,
                ]);

                $details = $componentData['details'] ?? [];
                if (is_array($details) && $details !== []) {
                    foreach ($details as $detailData) {
                        if (! is_array($detailData)) {
                            continue;
                        }

                        AssessmentComponentDetail::query()->create([
                            'assessment_component_id' => $component->getKey(),
                            'name' => $detailData['name'] ?? '',
                            'weight' => $detailData['weight'] ?? null,
                            'max_points' => 100.00,
                        ]);
                    }
                } else {
                    AssessmentComponentDetail::query()->create([
                        'assessment_component_id' => $component->getKey(),
                        'name' => $component->name,
                        'weight' => 100.00,
                        'max_points' => 100.00,
                    ]);
                }
            }

            if (! empty($data['is_default'])) {
                SyllabusTemplate::query()
                    ->where('unit_id', $template->unit_id)
                    ->whereKeyNot($template->getKey())
                    ->update(['is_default' => false]);

                $template->forceFill(['is_default' => true])->save();
            }

            return $template->fresh(['unit', 'assessmentComponents.details']);
        });
    }
}
