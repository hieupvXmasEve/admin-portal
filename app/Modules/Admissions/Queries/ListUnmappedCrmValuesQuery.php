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
 * No campus scoping: `manage_crm_value_mapping` is already an org-wide-only
 * permission (never granted to a campus-scoped role — enforced by seeder and
 * tested), so it is the sole authorization boundary for this screen. An
 * earlier version additionally scoped rows by the viewer's *currently
 * selected* campus (`app('campus')`, bound by `SetCampus` whenever
 * `session('current_campus_id')` is set — true for essentially every normal
 * staff session) — that hid every `kind=campus` row from every real user,
 * since an unmapped campus value has no `campus_code` to match against by
 * definition. Fixed 2026-08-10: this screen always shows everything.
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
    public function handle(): array
    {
        $rows = [];

        foreach (self::KIND_COLUMNS as $kind => $column) {
            $rows = [...$rows, ...$this->unmappedForKind($kind, $column)];
        }

        return $rows;
    }

    /** @return list<array{kind: string, crm_value: string, affected_count: int}> */
    private function unmappedForKind(string $kind, string $column): array
    {
        return DB::table('student_applications')
            ->leftJoin('crm_value_mappings', function ($join) use ($kind, $column) {
                $join->on('crm_value_mappings.crm_value', '=', "student_applications.{$column}")
                    ->where('crm_value_mappings.kind', $kind);
            })
            ->where('student_applications.status', 'pending')
            ->whereNotNull("student_applications.{$column}")
            ->where("student_applications.{$column}", '!=', '')
            ->where(function ($query) {
                $query->whereNull('crm_value_mappings.id')->orWhereNull('crm_value_mappings.local_code');
            })
            ->selectRaw("student_applications.{$column} as crm_value, COUNT(*) as affected_count")
            ->groupBy("student_applications.{$column}")
            ->get()
            ->map(fn ($row): array => ['kind' => $kind, 'crm_value' => (string) $row->crm_value, 'affected_count' => (int) $row->affected_count])
            ->all();
    }
}
