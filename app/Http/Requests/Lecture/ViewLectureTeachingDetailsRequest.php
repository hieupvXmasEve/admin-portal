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
        // No preparation needed for single select
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_type' => 'nullable|string|in:general,egc,semi,ai,mkt,ba,cs,ee,me,fin',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort' => 'nullable|string|in:date,duration',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
