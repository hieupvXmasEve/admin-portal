<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Queries;

use App\Modules\Admissions\Models\CrmValueMapping;

/**
 * Lists CRM value mappings staff have already resolved (excludes the
 * `intake` singleton, which has its own dedicated section), so a saved
 * mapping — e.g. campus_code — stays visible for reconfiguration instead of
 * disappearing once {@see ListUnmappedCrmValuesQuery} stops surfacing it.
 */
final class ListMappedCrmValuesQuery
{
    /** @return list<array{kind: string, crm_value: string, local_code: string}> */
    public function handle(): array
    {
        return CrmValueMapping::query()
            ->whereNotIn('kind', [CrmValueMapping::KIND_INTAKE, CrmValueMapping::KIND_INTAKE_COHORT])
            ->whereNotNull('local_code')
            ->orderBy('kind')
            ->orderBy('crm_value')
            ->get(['kind', 'crm_value', 'local_code'])
            ->map(fn (CrmValueMapping $mapping): array => [
                'kind' => $mapping->kind,
                'crm_value' => $mapping->crm_value,
                'local_code' => $mapping->local_code,
            ])
            ->all();
    }
}
