<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use Illuminate\Foundation\Http\FormRequest;

final class ExportApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['format' => ['required', 'in:xlsx,csv'], 'scope' => ['required', 'in:filtered,all'], 'search' => ['nullable', 'string'], 'status' => ['nullable', 'string'], 'campus_code' => ['nullable', 'string'], 'intake' => ['nullable', 'string'], 'sort' => ['nullable', 'in:created_at,full_name,student_code,email,intake,status'], 'direction' => ['nullable', 'in:asc,desc']];
    }
}
