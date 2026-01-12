<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Placement;

use App\Enums\ProgressionTriggerSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateEnglishLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'new_level' => ['required', 'integer', 'min:0', 'max:5'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'trigger_source' => ['nullable', 'string', new Enum(ProgressionTriggerSource::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required.',
            'student_id.exists' => 'Student not found.',
            'new_level.required' => 'New English level is required.',
            'new_level.min' => 'English level must be at least 0.',
            'new_level.max' => 'English level cannot exceed 5.',
            'semester_id.required' => 'Semester is required.',
            'semester_id.exists' => 'Semester not found.',
        ];
    }

    public function validatedWithUser(): array
    {
        return array_merge($this->validated(), [
            'created_by_user_id' => $this->user()->id,
        ]);
    }
}
