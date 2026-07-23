<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use Illuminate\Foundation\Http\FormRequest;

final class ListApplicationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string'], 'status' => ['nullable', 'string'], 'intake' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:200'], 'sort' => ['nullable', 'in:created_at,full_name,student_code,email,intake,status'], 'direction' => ['nullable', 'in:asc,desc']];
    }
}
