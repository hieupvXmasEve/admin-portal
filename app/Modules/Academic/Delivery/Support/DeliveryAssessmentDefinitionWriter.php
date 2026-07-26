<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Shared\Contracts\Academic\AssessmentDefinitionWriter;

final class DeliveryAssessmentDefinitionWriter implements AssessmentDefinitionWriter
{
    public function types(): array
    {
        return AssessmentComponent::TYPES;
    }

    public function syncForSyllabusTemplate(int $syllabusTemplateId, array $components): void
    {
        $existingComponents = AssessmentComponent::query()
            ->where('syllabus_template_id', $syllabusTemplateId)
            ->with('details')
            ->get()
            ->keyBy('id');
        $keepComponentIds = [];

        foreach ($components as $index => $componentData) {
            if (! is_array($componentData)) {
                continue;
            }

            $componentId = $componentData['id'] ?? null;
            $component = $componentId !== null && isset($existingComponents[$componentId])
                ? $existingComponents[$componentId]
                : new AssessmentComponent(['syllabus_template_id' => $syllabusTemplateId]);
            $component->fill([
                'name' => $componentData['name'] ?? $component->name,
                'code' => $componentData['code'] ?? $component->code,
                'weight' => $componentData['weight'] ?? $component->weight ?? 0,
                'type' => $componentData['type'] ?? $component->type ?? 'other',
                'sort_order' => $index,
            ])->save();

            $keepComponentIds[] = $component->getKey();
            $this->syncDetails($component, $componentData['details'] ?? []);
        }

        $componentsToDelete = AssessmentComponent::query()->where('syllabus_template_id', $syllabusTemplateId);
        if ($keepComponentIds !== []) {
            $componentsToDelete->whereNotIn('id', $keepComponentIds);
        }

        $componentsToDelete->with('details')->get()->each(function (AssessmentComponent $component): void {
            $component->details()->delete();
            $component->delete();
        });
    }

    public function importForSyllabusTemplates(array $components, array $details): array
    {
        $results = [
            'assessment_components' => ['created' => 0, 'skipped' => 0],
            'assessment_details' => ['created' => 0, 'skipped' => 0],
            'errors' => [],
            'warnings' => [],
        ];

        foreach ($components as $component) {
            $row = (int) ($component['source_row'] ?? 0);
            $location = $this->sourceLocation($component, $row);
            $templateId = (int) ($component['syllabus_template_id'] ?? 0);
            $name = trim((string) ($component['name'] ?? ''));
            $type = trim((string) ($component['type'] ?? ''));

            if ($templateId <= 0 || $name === '' || $type === '') {
                $results['errors'][] = "{$location}: Unit code, component name, and type are required";

                continue;
            }

            if (! array_key_exists($type, AssessmentComponent::TYPES)) {
                $results['errors'][] = "{$location}: Invalid assessment type '{$type}'";

                continue;
            }

            $existing = AssessmentComponent::query()
                ->where('syllabus_template_id', $templateId)
                ->where('name', $name)
                ->first();
            if ($existing !== null) {
                $results['assessment_components']['skipped']++;
                $results['warnings'][] = "{$location}: Assessment component '{$name}' already exists";

                continue;
            }

            AssessmentComponent::query()->create([
                'syllabus_template_id' => $templateId,
                'name' => $name,
                'weight' => $component['weight'] ?? null,
                'type' => $type,
                'is_required_to_sit_final_exam' => (bool) ($component['is_required_to_sit_final_exam'] ?? false),
            ]);
            $results['assessment_components']['created']++;
        }

        foreach ($details as $detail) {
            $row = (int) ($detail['source_row'] ?? 0);
            $location = $this->sourceLocation($detail, $row);
            $templateId = (int) ($detail['syllabus_template_id'] ?? 0);
            $componentName = trim((string) ($detail['component_name'] ?? ''));
            $name = trim((string) ($detail['name'] ?? ''));

            if ($templateId <= 0 || $componentName === '' || $name === '') {
                $results['errors'][] = "{$location}: Unit code, component name, and detail name are required";

                continue;
            }

            $component = AssessmentComponent::query()
                ->where('syllabus_template_id', $templateId)
                ->where('name', $componentName)
                ->first();
            if ($component === null) {
                $results['errors'][] = "{$location}: Assessment component '{$componentName}' not found";

                continue;
            }

            $existing = AssessmentComponentDetail::query()
                ->where('assessment_component_id', $component->getKey())
                ->where('name', $name)
                ->first();
            if ($existing !== null) {
                $results['assessment_details']['skipped']++;
                $results['warnings'][] = "{$location}: Assessment detail '{$name}' already exists";

                continue;
            }

            AssessmentComponentDetail::query()->create([
                'assessment_component_id' => $component->getKey(),
                'name' => $name,
                'weight' => $detail['weight'] ?? null,
            ]);
            $results['assessment_details']['created']++;
        }

        return $results;
    }

    /** @param array<string, mixed> $row */
    private function sourceLocation(array $row, int $sourceRow): string
    {
        return sprintf('%s row %d', $row['source_sheet'] ?? 'Assessment worksheet', $sourceRow);
    }

    private function syncDetails(AssessmentComponent $component, mixed $details): void
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
                'max_points' => $detailData['max_points'] ?? $detail->max_points ?? 100.00,
            ])->save();
            $keepDetailIds[] = $detail->getKey();
        }

        $detailsToDelete = $component->details();
        if ($keepDetailIds !== []) {
            $detailsToDelete->whereNotIn('id', $keepDetailIds);
        }
        $detailsToDelete->delete();

        if ($keepDetailIds === []) {
            $component->details()->create([
                'name' => $component->name,
                'weight' => 100.00,
                'max_points' => 100.00,
            ]);
        }
    }
}
