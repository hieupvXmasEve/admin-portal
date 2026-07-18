<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Dng;

use App\Modules\Finance\Dng\Models\DngCampusMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDngCampusMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_finance_dng_campus_mappings') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider_code' => trim((string) $this->input('provider_code')),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $campus = app()->bound('campus') ? app('campus') : null;
        $mappingId = $campus?->id === null
            ? null
            : DngCampusMapping::query()->where('campus_id', $campus->id)->value('id');

        return [
            'provider_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('finance_dng_campus_mappings', 'provider_code')->ignore($mappingId),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'provider_code.required' => 'A DNG provider campus code is required.',
            'provider_code.unique' => 'This DNG provider campus code is already mapped to another campus.',
        ];
    }
}
