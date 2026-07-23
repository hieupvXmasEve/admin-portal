<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\SyllabusTemplate;
use Illuminate\Support\Facades\DB;

class UpdateSyllabusTemplateAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SyllabusTemplate $template, array $data): SyllabusTemplate
    {
        return DB::transaction(function () use ($template, $data): SyllabusTemplate {
            $template->fill($data)->save();

            if (isset($data['assessment_components']) && is_array($data['assessment_components'])) {
                $this->syncAssessmentComponents($template, $data['assessment_components']);
            }

            if (array_key_exists('is_default', $data) && $template->is_default) {
                $this->setDefaultForUnit($template);
            }

            return $template->fresh(['unit', 'assessmentComponents.details']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    private function syncAssessmentComponents(SyllabusTemplate $template, array $components): void
    {
        $existingComponents = $template->assessmentComponents()->get()->keyBy('id');
        $keepComponentIds = [];

        foreach ($components as $index => $componentData) {
            $componentId = $componentData['id'] ?? null;
            $component = $componentId !== null && isset($existingComponents[$componentId])
                ? $existingComponents[$componentId]
                : new AssessmentComponent(['syllabus_template_id' => $template->getKey()]);

            $component->fill([
                'name' => $componentData['name'] ?? $component->name,
                'code' => $componentData['code'] ?? $component->code,
                'weight' => $componentData['weight'] ?? $component->weight ?? 0,
                'type' => $componentData['type'] ?? $component->type ?? 'other',
                'sort_order' => $index,
            ])->save();

            $keepComponentIds[] = $component->getKey();
            $this->syncAssessmentDetails($component, $componentData['details'] ?? []);
        }

        $template->assessmentComponents()
            ->whereNotIn('id', $keepComponentIds)
            ->each(function (AssessmentComponent $component): void {
                $component->details()->delete();
                $component->delete();
            });
    }

    private function syncAssessmentDetails(AssessmentComponent $component, mixed $details): void
    {
        if (! is_array($details) || $details === []) {
            $component->details()->delete();
            $component->details()->create([
                'name' => $component->name,
                'weight' => 100.00,
                'max_points' => 100.00,
            ]);

            return;
        }

        $existingDetails = $component->details()->get()->keyBy('id');
        $keepDetailIds = [];
        foreach ($details as $detailData) {
            if (! is_array($detailData)) {
                continue;
            }

            $detailId = $detailData['id'] ?? null;
            $detail = $detailId !== null && isset($existingDetails[$detailId])
                ? $existingDetails[$detailId]
                : new AssessmentComponentDetail(['assessment_component_id' => $component->getKey()]);
            $detail->fill([
                'name' => $detailData['name'] ?? $detail->name ?? '',
                'weight' => $detailData['weight'] ?? $detail->weight,
            ])->save();
            $keepDetailIds[] = $detail->getKey();
        }

        $component->details()->whereNotIn('id', $keepDetailIds)->delete();

        if ($keepDetailIds === []) {
            $component->details()->create([
                'name' => $component->name,
                'weight' => 100.00,
                'max_points' => 100.00,
            ]);
        }
    }

    private function setDefaultForUnit(SyllabusTemplate $template): void
    {
        SyllabusTemplate::query()
            ->where('unit_id', $template->unit_id)
            ->whereKeyNot($template->getKey())
            ->update(['is_default' => false]);

        $template->forceFill(['is_default' => true])->save();
    }
}
