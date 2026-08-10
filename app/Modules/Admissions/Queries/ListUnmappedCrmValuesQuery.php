<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Queries;

use App\Modules\Admissions\Models\CrmValueMapping;
use Illuminate\Support\Facades\DB;

/**
 * Discovers raw CRM values with no resolved local code yet, from pending
 * applications only (backfill only ever touches pending rows, so an
 * "affected count" for anything else would be misleading). Nothing is
 * inserted here — the mapping table only ever holds staff decisions (plus the
 * Phase 1 seed).
 *
 * Campus scoping: kinds other than `campus` are attributed to the
 * application's own `campus_code`, so a campus-scoped actor only sees rows
 * for their campus. `campus` values have no attributable campus by
 * definition (that is the value being resolved) — a campus-scoped actor sees
 * none; only an unscoped (all-campus) actor does.
 */
final class ListUnmappedCrmValuesQuery
{
    /** @var array<string, string> kind => student_applications column */
    private const KIND_COLUMNS = [
        CrmValueMapping::KIND_CAMPUS => 'crm_campus',
        CrmValueMapping::KIND_MAJOR => 'crm_major',
        CrmValueMapping::KIND_SCHOLARSHIP => 'scholarship',
        CrmValueMapping::KIND_PATHWAY_GATEWAY => 'pathway_gateway',
        CrmValueMapping::KIND_UU_DAI_GC => 'uu_dai_gc',
    ];

    /**
     * @return list<array{kind: string, crm_value: string, affected_count: int}>
     */
    public function handle(?string $campusCode): array
    {
        $rows = [];

        foreach (self::KIND_COLUMNS as $kind => $column) {
            $rows = [...$rows, ...$this->unmappedForKind($kind, $column, $campusCode)];
        }

        return $rows;
    }

    /** @return list<array{kind: string, crm_value: string, affected_count: int}> */
    private function unmappedForKind(string $kind, string $column, ?string $campusCode): array
    {
        $query = DB::table('student_applications')
            ->leftJoin('crm_value_mappings', function ($join) use ($kind, $column) {
                $join->on('crm_value_mappings.crm_value', '=', "student_applications.{$column}")
                    ->where('crm_value_mappings.kind', $kind);
            })
            ->where('student_applications.status', 'pending')
            ->whereNotNull("student_applications.{$column}")
            ->where("student_applications.{$column}", '!=', '')
            ->where(function ($query) {
                $query->whereNull('crm_value_mappings.id')->orWhereNull('crm_value_mappings.local_code');
            });

        if ($kind === CrmValueMapping::KIND_CAMPUS) {
            // Unmapped campus values are never attributable to a campus; only
            // an unscoped (all-campus) actor may see them.
            if ($campusCode !== null) {
                return [];
            }
        } elseif ($campusCode !== null) {
            $query->where('student_applications.campus_code', $campusCode);
        }

        return $query->selectRaw("student_applications.{$column} as crm_value, COUNT(*) as affected_count")
            ->groupBy("student_applications.{$column}")
            ->get()
            ->map(fn ($row): array => ['kind' => $kind, 'crm_value' => (string) $row->crm_value, 'affected_count' => (int) $row->affected_count])
            ->all();
    }
}
