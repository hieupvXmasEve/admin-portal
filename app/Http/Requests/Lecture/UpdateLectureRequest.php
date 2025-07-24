<?php

namespace App\Http\Requests\Lecture;

use App\Models\Lecture;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLectureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit_lecturer');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = Lecture::validationRules();

        // Get the lecture being updated
        $lecture = $this->route('lecture');

        // Update unique validation rules for existing record
        if ($lecture) {
            $rules['employee_id'] = ['required', 'string', 'max:20', 'unique:lectures,employee_id,' . $lecture->id];
            $rules['email'] = ['required', 'email', 'unique:lectures,email,' . $lecture->id];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return Lecture::validationMessages();
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'employee ID',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'email' => 'email address',
            'campus_id' => 'campus',
            'academic_rank' => 'academic rank',
            'hire_date' => 'hire date',
            'employment_type' => 'employment type',
            'employment_status' => 'employment status',
            'phone' => 'phone number',
            'mobile_phone' => 'mobile phone',
            'department' => 'department',
            'faculty' => 'faculty',
            'specialization' => 'specialization',
            'expertise_areas' => 'expertise areas',
            'highest_degree' => 'highest degree',
            'degree_field' => 'degree field',
            'alma_mater' => 'alma mater',
            'graduation_year' => 'graduation year',
            'contract_start_date' => 'contract start date',
            'contract_end_date' => 'contract end date',
            'preferred_teaching_days' => 'preferred teaching days',
            'preferred_start_time' => 'preferred start time',
            'preferred_end_time' => 'preferred end time',
            'max_teaching_hours_per_week' => 'maximum teaching hours per week',
            'teaching_modalities' => 'teaching modalities',
            'office_address' => 'office address',
            'office_phone' => 'office phone',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_phone' => 'emergency contact phone',
            'emergency_contact_relationship' => 'emergency contact relationship',
            'biography' => 'biography',
            'certifications' => 'certifications',
            'languages' => 'languages',
            'hourly_rate' => 'hourly rate',
            'salary' => 'salary',
            'is_active' => 'active status',
            'can_teach_online' => 'online teaching capability',
            'is_available_for_assignment' => 'availability for assignment',
            'notes' => 'notes',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string boolean values to actual booleans
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'can_teach_online' => $this->boolean('can_teach_online', false),
            'is_available_for_assignment' => $this->boolean('is_available_for_assignment', true),
        ]);

        // Ensure arrays are properly formatted
        if ($this->has('expertise_areas') && is_string($this->expertise_areas)) {
            $this->merge(['expertise_areas' => json_decode($this->expertise_areas, true) ?: []]);
        }

        if ($this->has('preferred_teaching_days') && is_string($this->preferred_teaching_days)) {
            $this->merge(['preferred_teaching_days' => json_decode($this->preferred_teaching_days, true) ?: []]);
        }

        if ($this->has('teaching_modalities') && is_string($this->teaching_modalities)) {
            $this->merge(['teaching_modalities' => json_decode($this->teaching_modalities, true) ?: []]);
        }

        if ($this->has('certifications') && is_string($this->certifications)) {
            $this->merge(['certifications' => json_decode($this->certifications, true) ?: []]);
        }

        if ($this->has('languages') && is_string($this->languages)) {
            $this->merge(['languages' => json_decode($this->languages, true) ?: []]);
        }
    }
}
