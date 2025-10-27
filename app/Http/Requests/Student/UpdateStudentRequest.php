<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TODO: Add proper authorization check
    }

    public function rules(): array
    {
        $rules = Student::validationRules();

        // Modify unique rules to exclude current student
        $studentId = $this->route('student')->id;

        $rules['email'] = [
            'required',
            'string',
            'email',
            'max:255',
            Rule::unique('students')->ignore($studentId),
        ];

        if (isset($rules['national_id'])) {
            $rules['national_id'] = [
                'nullable',
                'string',
                'max:20',
                Rule::unique('students')->ignore($studentId),
            ];
        }

        // Add GC level fields - nullable by default
        $rules['gc_starting_level'] = ['nullable', 'integer', 'min:0', 'max:6'];
        $rules['gc_current_level'] = ['nullable', 'integer', 'min:0', 'max:6'];
        $rules['gc_total_levels'] = ['nullable', 'integer', 'min:0'];

        // Conditional validation for GC levels when status is intake_pre_uni_gc
        if ($this->input('status') === 'intake_pre_uni_gc') {
            $rules['gc_starting_level'] = ['required', 'integer', 'min:0', 'max:6'];
            $rules['gc_current_level'] = ['required', 'integer', 'min:0', 'max:6'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return Student::validationMessages();
    }

    protected function prepareForValidation(): void
    {
        // Clean and format data before validation
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[^0-9+]/', '', $this->phone),
            ]);
        }

        if ($this->has('national_id')) {
            $this->merge([
                'national_id' => preg_replace('/[^0-9]/', '', $this->national_id),
            ]);
        }

        // Ensure proper date format
        if ($this->has('date_of_birth') && $this->date_of_birth) {
            $this->merge([
                'date_of_birth' => date('Y-m-d', strtotime($this->date_of_birth)),
            ]);
        }

        if ($this->has('admission_date') && $this->admission_date) {
            $this->merge([
                'admission_date' => date('Y-m-d', strtotime($this->admission_date)),
            ]);
        }
    }
}
