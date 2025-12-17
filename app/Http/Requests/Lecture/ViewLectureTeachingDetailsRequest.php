<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use Illuminate\Foundation\Http\FormRequest;

class ViewLectureTeachingDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('view_lecturer');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('course_offering_ids') && ! is_array($this->input('course_offering_ids'))) {
            $this->merge([
                'course_offering_ids' => explode(',', (string) $this->input('course_offering_ids')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'course_offering_ids' => 'nullable|array',
            'course_offering_ids.*' => 'integer|exists:course_offerings,id',
            'date_from' => 'nullable|date|after_or_equal:2025-01-01',
            'date_to' => 'nullable|date|before_or_equal:today',
            'sort' => 'nullable|string|in:date,duration',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
