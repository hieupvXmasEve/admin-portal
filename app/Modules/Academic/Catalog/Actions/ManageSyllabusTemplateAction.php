<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\SyllabusTemplate;
use Illuminate\Support\Facades\DB;

class ManageSyllabusTemplateAction
{
    public function delete(SyllabusTemplate $template): void
    {
        $template->delete();
    }

    public function toggleActive(SyllabusTemplate $template): SyllabusTemplate
    {
        $template->forceFill(['is_active' => ! $template->is_active])->save();

        return $template->fresh();
    }

    public function setDefault(SyllabusTemplate $template): SyllabusTemplate
    {
        return DB::transaction(function () use ($template): SyllabusTemplate {
            SyllabusTemplate::query()
                ->where('unit_id', $template->unit_id)
                ->whereKeyNot($template->getKey())
                ->update(['is_default' => false]);

            $template->forceFill(['is_default' => true])->save();

            return $template->fresh();
        });
    }

    public function clone(SyllabusTemplate $template, ?int $actorId): SyllabusTemplate
    {
        return DB::transaction(function () use ($template, $actorId): SyllabusTemplate {
            $clone = $template->replicate([
                'is_default',
                'is_active',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);
            $clone->forceFill([
                'is_default' => false,
                'is_active' => true,
                'created_by' => $actorId,
                'source_template_id' => $template->getKey(),
                'version' => $this->nextVersion($template->version),
            ])->save();

            return $clone;
        });
    }

    private function nextVersion(?string $current): string
    {
        if (empty($current)) {
            return '1.0';
        }

        $parts = array_map('intval', explode('.', $current));
        $parts[array_key_last($parts)] = ($parts[array_key_last($parts)] ?? 0) + 1;

        return implode('.', $parts);
    }
}
