<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\SyllabusTemplate;
use App\Shared\Contracts\Academic\AssessmentDefinitionWriter;
use Illuminate\Support\Facades\DB;

class CreateSyllabusTemplateAction
{
    public function __construct(private readonly AssessmentDefinitionWriter $assessmentDefinitions) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): SyllabusTemplate
    {
        return DB::transaction(function () use ($data): SyllabusTemplate {
            $template = SyllabusTemplate::query()->create($data);

            $components = $data['assessment_components'] ?? [];
            if (is_array($components)) {
                $this->assessmentDefinitions->syncForSyllabusTemplate($template->getKey(), $components);
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
