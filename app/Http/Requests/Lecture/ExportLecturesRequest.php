<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use App\Models\Campus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportLecturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export_lecturer') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'campus_id' => ['nullable', Rule::exists(Campus::class, 'id')],
            'semester_id' => ['nullable', 'string', 'max:20'],
            'unit_type' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['nullable', Rule::in(['active', 'on_leave', 'sabbatical', 'retired', 'terminated', 'suspended', 'all'])],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'visiting', 'emeritus', 'all'])],
            'department' => ['nullable', 'string', 'max:100'],
            'academic_rank' => ['nullable', Rule::in(['lecturer', 'senior_lecturer', 'associate_professor', 'professor', 'emeritus_professor', 'visiting_lecturer', 'adjunct_professor', 'all'])],
            'available_for_assignment' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
