<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use App\Modules\Admissions\Support\StudentApplicationFilterRules;
use App\Modules\Admissions\Support\StudentApplicationSortColumns;
use Illuminate\Foundation\Http\FormRequest;

final class ExportApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // No `scope` (D11): the UI only ever sent `scope=filtered`, and
        // `scope=all` bypassed every filter via URL edit. Filters always apply now.
        // No `campus_code` either: nothing ever sent it (Index.vue never did),
        // and it duplicated the session's campus scoping as an unused surface.
        return array_merge([
            'format' => ['required', 'in:xlsx,csv'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'intake' => ['nullable', 'string'],
            'sort' => ['nullable', StudentApplicationSortColumns::rule()],
            'direction' => ['nullable', 'in:asc,desc'],
        ], StudentApplicationFilterRules::rules());
    }
}
