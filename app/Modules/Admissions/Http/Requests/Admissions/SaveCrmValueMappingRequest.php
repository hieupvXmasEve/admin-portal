<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use App\Modules\Admissions\Models\CrmValueMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `local_code` is validated against the live catalog for `campus`/`major`/
 * `intake` (the only kinds a real code must exist for). `scholarship`,
 * `pathway_gateway`, `uu_dai_gc` have no local catalog (D8) — any non-empty
 * string is accepted, since they never block conversion.
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
            default => null,
        };
    }
}
