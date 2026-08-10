<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support\Crm;

use App\Modules\Admissions\Models\CrmValueMapping;

/**
 * Sole read/write surface for the single target-intake setting, stored as
 * `crm_value_mappings (kind='intake', crm_value='__default__')` rather than
 * in `system_settings` (Platform-owned, closed key registry — see
 * phase-01-schema-and-storage.md §4). Keeping every read/write behind this
 * one class is what keeps "one global intake" a one-line change later
 * (plan.md open question 2).
 */
final class CrmMappingSettings
{
    public function getIntakeCode(): ?string
    {
        return CrmValueMapping::query()
            ->where('kind', CrmValueMapping::KIND_INTAKE)
            ->where('crm_value', CrmValueMapping::INTAKE_DEFAULT_KEY)
            ->value('local_code');
    }

    public function setIntakeCode(string $semesterCode): void
    {
        CrmValueMapping::query()->updateOrCreate(
            ['kind' => CrmValueMapping::KIND_INTAKE, 'crm_value' => CrmValueMapping::INTAKE_DEFAULT_KEY],
            ['local_code' => $semesterCode],
        );
    }
}
