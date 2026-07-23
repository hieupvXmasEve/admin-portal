<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListAcademicPeriodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter', []);

        foreach (['is_active', 'is_archived'] as $key) {
            if (($filter[$key] ?? null) === 'true') {
                $filter[$key] = true;
            } elseif (($filter[$key] ?? null) === 'false') {
                $filter[$key] = false;
            } elseif (($filter[$key] ?? null) === 'null' || ($filter[$key] ?? null) === '') {
                $filter[$key] = null;
            }
        }

        $this->merge(['filter' => $filter]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'filter.name' => ['nullable', 'string', 'max:255'],
            'filter.year' => ['nullable', 'string', 'max:4'],
            'filter.is_active' => ['nullable', 'boolean'],
            'filter.is_archived' => ['nullable', 'boolean'],
        ];
    }
}
