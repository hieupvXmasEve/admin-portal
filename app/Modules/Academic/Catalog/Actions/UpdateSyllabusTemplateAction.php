<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\SyllabusTemplate;
use App\Shared\Contracts\Academic\AssessmentDefinitionWriter;
use Illuminate\Support\Facades\DB;

class UpdateSyllabusTemplateAction
{
    public function __construct(private readonly AssessmentDefinitionWriter $assessmentDefinitions) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SyllabusTemplate $template, array $data): SyllabusTemplate
    {
        return DB::transaction(function () use ($template, $data): SyllabusTemplate {
            $template->fill($data)->save();

            if (isset($data['assessment_components']) && is_array($data['assessment_components'])) {
                $this->assessmentDefinitions->syncForSyllabusTemplate(
                    $template->getKey(),
                    $data['assessment_components'],
                );
            }

            if (array_key_exists('is_default', $data) && $template->is_default) {
                $this->setDefaultForUnit($template);
            }

            return $template->fresh(['unit', 'assessmentComponents.details']);
        });
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
