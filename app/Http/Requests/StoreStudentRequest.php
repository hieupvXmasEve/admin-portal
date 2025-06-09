<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TODO: Add proper authorization check
    }

    public function rules(): array
    {
        return Student::validationRules();
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
                'phone' => preg_replace('/[^0-9+]/', '', $this->phone)
            ]);
        }

        if ($this->has('national_id')) {
            $this->merge([
                'national_id' => preg_replace('/[^0-9]/', '', $this->national_id)
            ]);
        }

        // Ensure proper date format
        if ($this->has('date_of_birth') && $this->date_of_birth) {
            $this->merge([
                'date_of_birth' => date('Y-m-d', strtotime($this->date_of_birth))
            ]);
        }

        if ($this->has('admission_date') && $this->admission_date) {
            $this->merge([
                'admission_date' => date('Y-m-d', strtotime($this->admission_date))
            ]);
        }
    }
}
