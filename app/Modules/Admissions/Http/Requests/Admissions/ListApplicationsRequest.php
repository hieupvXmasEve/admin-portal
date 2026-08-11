<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use App\Modules\Admissions\Support\StudentApplicationFilterRules;
use App\Modules\Admissions\Support\StudentApplicationSortColumns;
use Illuminate\Foundation\Http\FormRequest;

final class ListApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'intake' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sort' => ['nullable', StudentApplicationSortColumns::rule()],
            'direction' => ['nullable', 'in:asc,desc'],
        ], StudentApplicationFilterRules::rules());
    }
}
