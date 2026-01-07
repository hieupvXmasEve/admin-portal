<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Models\Student;
use App\Models\User;
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

        // Add parent user validation rules
        $student = $this->route('student');
        $studentId = $student->id;

        // Ensure parent_user relationship is loaded
        if (! $student->relationLoaded('parentUser')) {
            $student->load('parentUser');
        }

        $parentUserId = $student->parent_user_id;

        $rules['parent_name'] = ['nullable', 'string', 'max:255'];

        // Build parent_email validation rules
        // Note: nullable rule must come before unique to skip unique check when value is null
        $parentEmailRules = [
            'nullable',
            'string',
            'email',
            'max:255',
        ];

        // Custom validation: Check if email is already used by another student's parent
        // One user can only be parent of one student
        $parentEmailRules[] = function ($attribute, $value, $fail) use ($studentId, $parentUserId) {
            if (empty($value)) {
                return; // Skip validation if empty (nullable)
            }

            // Find user by email
            $user = User::where('email', $value)->first();

            if ($user) {
                // Check if this user is already a parent of another student
                $existingStudent = \App\Models\Student::where('parent_user_id', $user->id)
                    ->where('id', '!=', $studentId)
                    ->first();

                if ($existingStudent) {
                    $fail('This email is already linked to another student as parent.');
                }

                // If student has a parent_user_id, allow updating the same parent
                // But if trying to change to a different user that's already linked, reject
                if ($parentUserId && $parentUserId !== $user->id && $existingStudent) {
                    $fail('This email is already linked to another student as parent.');
                }
            }
        };

        // Validate email uniqueness in users table (but allow if it's the current parent)
        if ($parentUserId) {
            // Student has a parent user - validate unique but ignore current parent
            $parentEmailRules[] = Rule::unique('users', 'email')->ignore($parentUserId);
        } else {
            // If no parent_user_id, still validate unique in users table
            // But the custom validation above will check if it's already linked to another student
            $parentEmailRules[] = Rule::unique('users', 'email');
        }

        $rules['parent_email'] = $parentEmailRules;

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

        // Convert empty strings to null for parent user fields
        if ($this->has('parent_email') && $this->parent_email === '') {
            $this->merge([
                'parent_email' => null,
            ]);
        }

        if ($this->has('parent_name') && $this->parent_name === '') {
            $this->merge([
                'parent_name' => null,
            ]);
        }

        // Convert empty strings to null for address and ethnicity fields
        $addressFields = [
            'ethnicity',
            'current_address_line',
            'current_ward',
            'current_province',
            'current_country',
            'cccd_address_line',
            'cccd_ward',
            'cccd_province',
            'cccd_country',
        ];

        foreach ($addressFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        // Convert all string fields to lowercase for data consistency
        // Exclude fields that should not be lowercase: phone (already cleaned), national_id (already cleaned),
        // date fields, numeric fields, and fields that are already null
        $stringFieldsToLowercase = [
            'email',
            'full_name',
            'nationality',
            'ethnicity',
            'gender',
            'status',
            'academic_status',
            'address',
            'current_address_line',
            'current_ward',
            'current_province',
            'current_country',
            'cccd_address',
            'cccd_address_line',
            'cccd_ward',
            'cccd_province',
            'cccd_country',
            'emergency_contact_name',
            'emergency_contact_relationship',
            'emergency_contact_email',
            'emergency_contact_name_1',
            'emergency_contact_email_1',
            'emergency_contact_relationship_1',
            'high_school_name',
            'admission_notes',
            'status_reason',
            'parent_email',
            'parent_name',
            'intake_mode',
        ];

        foreach ($stringFieldsToLowercase as $field) {
            if ($this->has($field) && is_string($this->input($field)) && $this->input($field) !== '') {
                $this->merge([
                    $field => mb_strtolower($this->input($field), 'UTF-8'),
                ]);
            }
        }
    }
}
