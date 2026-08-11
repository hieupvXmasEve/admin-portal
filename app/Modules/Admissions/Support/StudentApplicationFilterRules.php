<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support;

use App\Modules\Admissions\Queries\ListApplicationsQuery;
use Illuminate\Validation\Rule;

/**
 * Advanced-filter validation rules shared by `ListApplicationsRequest` and
 * `ExportApplicationsRequest`, so the export can never accept a filter the
 * list rejects (or vice versa) — see {@see ListApplicationsQuery::ADVANCED_FILTER_KEYS}.
 */
final class StudentApplicationFilterRules
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            // Static enums — Swinx-owned, closed by design
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'synced' => ['nullable', Rule::in(['all', 'synced', 'not_synced'])],

            // Data-derived — shape only (D6): an unmatched value returns zero rows, not a
            // 422. Includes gpa_type/crm_major/etc — CRM-owned free text whose value set
            // grows independently of a deploy, so it gets the same treatment as province/school.
            'gpa_type' => ['nullable', 'string', 'max:255'],
            'ethnicity' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'crm_major' => ['nullable', 'string', 'max:255'],
            'scholarship' => ['nullable', 'string', 'max:255'],
            'pathway_gateway' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'string', 'max:255'],
            'school' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'english_test_type' => ['nullable', 'string', 'max:255'],

            // Numeric ranges
            'gpa_min' => ['nullable', 'numeric', 'min:0'],
            'gpa_max' => ['nullable', 'numeric', 'min:0'],
            'overall_min' => ['nullable', 'numeric', 'min:0'],
            'overall_max' => ['nullable', 'numeric', 'min:0'],
            'paid_min' => ['nullable', 'numeric', 'min:0'],
            'paid_max' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
