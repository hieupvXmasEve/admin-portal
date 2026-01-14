<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TODO: Add proper permission check for student creation
        return true; // For now, allow all authenticated users
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:students,email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            'nationality' => ['required', 'string', 'max:100'],
            'national_id' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'campus_id' => ['required', 'exists:campuses,id'], // Optional if taken from session
            'program_id' => ['required', 'exists:programs,id'],
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
            'admission_date' => ['required', 'date', 'before_or_equal:today'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'high_school_name' => ['required', 'string', 'max:255'],
            'high_school_graduation_year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'entrance_exam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admission_notes' => ['nullable', 'string'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Full name is required',
            'full_name.max' => 'Full name cannot exceed 100 characters',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already registered',
            'email.max' => 'Email cannot exceed 255 characters',

            'phone.max' => 'Phone number cannot exceed 20 characters',
            'gender.in' => 'Gender must be male, female, or other',
            'nationality.max' => 'Nationality cannot exceed 100 characters',
            'national_id.max' => 'National ID cannot exceed 20 characters',
            'program_id.required' => 'Program selection is required',
            'program_id.exists' => 'Selected program is invalid',
            'curriculum_version_id.required' => 'Curriculum version is required',
            'curriculum_version_id.exists' => 'Selected curriculum version is invalid',
            'admission_date.required' => 'Admission date is required',
            'admission_date.date' => 'Please provide a valid admission date',
            'admission_date.before_or_equal' => 'Admission date cannot be in the future',
            // 'expected_graduation_date.date' => 'Please provide a valid expected graduation date',
            // 'expected_graduation_date.after' => 'Expected graduation date must be after admission date',
            'emergency_contact_name.max' => 'Emergency contact name cannot exceed 255 characters',
            'emergency_contact_phone.max' => 'Emergency contact phone cannot exceed 20 characters',
            'emergency_contact_relationship.max' => 'Emergency contact relationship cannot exceed 100 characters',
            'high_school_name.max' => 'High school name cannot exceed 255 characters',
            'high_school_graduation_year.min' => 'High school graduation year must be after 1900',
            'high_school_graduation_year.max' => 'High school graduation year cannot be in the distant future',
            'entrance_exam_score.numeric' => 'Entrance exam score must be a number',
            'entrance_exam_score.min' => 'Entrance exam score cannot be negative',
            'entrance_exam_score.max' => 'Entrance exam score cannot exceed 100',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Format dates
        if ($this->has('admission_date') && $this->admission_date) {
            $this->merge([
                'admission_date' => date('Y-m-d', strtotime($this->admission_date)),
            ]);
        }

        // if ($this->has('expected_graduation_date') && $this->expected_graduation_date) {
        //     $this->merge([
        //         'expected_graduation_date' => date('Y-m-d', strtotime($this->expected_graduation_date))
        //     ]);
        // }

        if ($this->has('date_of_birth') && $this->date_of_birth) {
            $this->merge([
                'date_of_birth' => date('Y-m-d', strtotime($this->date_of_birth)),
            ]);
        }

        // Clean parent email
        if ($this->has('parent_email') && $this->parent_email) {
            $this->merge([
                'parent_email' => strtolower(trim($this->parent_email)),
            ]);
        }

        // Use session campus_id if not provided
        if (! $this->has('campus_id') || ! $this->campus_id) {
            $this->merge([
                'campus_id' => session()->get('current_campus_id'),
            ]);
        }
    }
}
