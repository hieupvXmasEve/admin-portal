<?php

declare(strict_types=1);

namespace App\Actions\SyllabusTemplate;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\SyllabusTemplate;
use Illuminate\Support\Facades\DB;

class CreateSyllabusTemplateAction
{
    /**
     * Create a new syllabus template with its assessment components and details.
     */
    public function execute(array $data): SyllabusTemplate
    {
        return DB::transaction(function () use ($data) {
            $template = SyllabusTemplate::create($data);

            // Create assessment components if provided
            $components = $data['assessment_components'] ?? [];
            if (is_array($components) && ! empty($components)) {
                foreach ($components as $idx => $comp) {
                    if (! is_array($comp)) {
                        continue;
                    }

                    $component = new AssessmentComponent([
                        'name' => $comp['name'] ?? null,
                        'code' => $comp['code'] ?? null,
                        'weight' => $comp['weight'] ?? 0,
                        'type' => $comp['type'] ?? 'other',
                        'sort_order' => $idx,
                    ]);
                    $component->syllabus_template_id = $template->getKey();
                    $component->save();

                    $details = $comp['details'] ?? [];
                    if (is_array($details) && ! empty($details)) {
                        foreach ($details as $d) {
                            if (! is_array($d)) {
                                continue;
                            }
                            AssessmentComponentDetail::create([
                                'assessment_component_id' => $component->getKey(),
                                'name' => $d['name'] ?? '',
                                'weight' => $d['weight'] ?? null,
                                'max_points' => 100.00,
                            ]);
                        }
                    } else {
                        // Create default detail if no details provided
                        AssessmentComponentDetail::create([
                            'assessment_component_id' => $component->getKey(),
                            'name' => $component->name,
                            'weight' => 100.00,
                            'max_points' => 100.00,
                        ]);
                    }
                }
            }

            if (! empty($data['is_default'])) {
                $this->setDefaultForUnit($template);
            }

            return $template->fresh(['unit', 'assessmentComponents.details']);
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
