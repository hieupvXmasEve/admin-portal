<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Placement;

use App\Enums\ProgressionTriggerSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class InitializePlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $baseRules = [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'has_ielts' => ['required', 'boolean'],
            'trigger_source' => ['nullable', 'string', new Enum(ProgressionTriggerSource::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        $hasIelts = $this->boolean('has_ielts');

        if ($hasIelts) {
            return array_merge($baseRules, $this->ieltsRules());
        }

        return array_merge($baseRules, $this->placementTestRules());
    }

    protected function ieltsRules(): array
    {
        return [
            'ielts_score' => ['required', 'numeric', 'min:0', 'max:9'],
            'upload_record_id' => ['nullable', 'integer', 'exists:upload_records,id'],
            'missing_documents' => ['nullable', 'boolean'],
            'issue_date' => ['nullable', 'date'],
            'ielts_notes' => ['nullable', 'string', 'max:2000'],
            'english_level' => ['nullable', 'integer', 'min:0', 'max:5'],
        ];
    }

    protected function placementTestRules(): array
    {
        return [
            'english_level' => ['required', 'integer', 'min:0', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required.',
            'student_id.exists' => 'Student not found.',
            'semester_id.required' => 'Semester is required.',
            'semester_id.exists' => 'Semester not found.',
            'has_ielts.required' => 'Please specify whether student has IELTS.',
            'ielts_score.required' => 'IELTS score is required when student has IELTS.',
            'ielts_score.min' => 'IELTS score must be at least 0.',
            'ielts_score.max' => 'IELTS score cannot exceed 9.',
            'english_level.required' => 'English level is required for placement test.',
            'english_level.min' => 'English level must be at least 0.',
            'english_level.max' => 'English level cannot exceed 5.',
        ];
    }

    public function validatedWithUser(): array
    {
        return array_merge($this->validated(), [
            'created_by_user_id' => $this->user()->id,
        ]);
    }
}
