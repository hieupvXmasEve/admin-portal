<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use App\Models\Campus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAvailableLecturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'campus_id' => ['nullable', Rule::exists(Campus::class, 'id')],
            'is_active' => ['nullable', 'string', Rule::in(['true', 'false', '1', '0'])],
            'is_available_for_assignment' => ['nullable', 'string', Rule::in(['true', 'false', '1', '0'])],
            'employment_status' => ['nullable', Rule::in(['active', 'on_leave', 'sabbatical', 'retired', 'terminated', 'suspended'])],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'visiting', 'emeritus'])],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
