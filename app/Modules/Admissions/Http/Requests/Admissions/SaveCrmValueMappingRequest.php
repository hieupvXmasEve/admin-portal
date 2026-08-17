<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use App\Modules\Admissions\Models\CrmValueMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `local_code` is validated against a live catalog for every kind that has
 * one: `campus`→`campuses`, `major`→`programs`, `intake`→`semesters`,
 * `scholarship`→`scholarship_definitions`, and both `pathway_gateway` and
 * `uu_dai_gc`→`voucher_definitions` (confirmed against live data: CRM's
 * "Taiwan Gateway"/"Taiwan Pathway" and "50% học phí kỳ GC" values match
 * `voucher_definitions` rows exactly — D8's "no local catalog" claim for
 * these three kinds was wrong, corrected 2026-08-10). Catalog validation is
 * orthogonal to whether a kind blocks conversion (it still doesn't, for
 * these three) — it only prevents saving a code that cannot resolve to
 * anything.
 */
final class SaveCrmValueMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in([
                CrmValueMapping::KIND_CAMPUS,
                CrmValueMapping::KIND_MAJOR,
                CrmValueMapping::KIND_SPECIALIZATION,
                CrmValueMapping::KIND_SCHOLARSHIP,
                CrmValueMapping::KIND_PATHWAY_GATEWAY,
                CrmValueMapping::KIND_UU_DAI_GC,
                CrmValueMapping::KIND_INTAKE,
            ])],
            'crm_value' => ['required', 'string', 'max:255'],
            'local_code' => ['required', 'string', 'max:255'],
            // Cohort number (khóa) rides along with the intake save so a new
            // admission round can never set a target intake without declaring
            // which cohort its converted students belong to.
            'cohort' => ['required_if:kind,'.CrmValueMapping::KIND_INTAKE, 'nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $kind = $this->input('kind');
            $localCode = $this->input('local_code');
            $catalogRule = $this->catalogExistsRuleFor($kind);

            if ($catalogRule === null || $localCode === null) {
                return;
            }

            $exists = DB::table($catalogRule['table'])->where($catalogRule['column'], $localCode)->exists();
            if (! $exists) {
                $validator->errors()->add('local_code', "The selected {$kind} code does not exist.");
            }
        });
    }

    /** @return array{table: string, column: string}|null */
    private function catalogExistsRuleFor(?string $kind): ?array
    {
        return match ($kind) {
            CrmValueMapping::KIND_CAMPUS => ['table' => 'campuses', 'column' => 'code'],
            CrmValueMapping::KIND_MAJOR => ['table' => 'programs', 'column' => 'code'],
            CrmValueMapping::KIND_SPECIALIZATION => ['table' => 'specializations', 'column' => 'code'],
            CrmValueMapping::KIND_INTAKE => ['table' => 'semesters', 'column' => 'code'],
            CrmValueMapping::KIND_SCHOLARSHIP => ['table' => 'scholarship_definitions', 'column' => 'code'],
            CrmValueMapping::KIND_PATHWAY_GATEWAY, CrmValueMapping::KIND_UU_DAI_GC => ['table' => 'voucher_definitions', 'column' => 'code'],
            default => null,
        };
    }
}
