<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
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
        // Only allow updating fields that are displayed on the UI
        $studentRouteParameter = $this->route('student');
        $student = $studentRouteParameter instanceof Student
            ? $studentRouteParameter
            : Student::query()->findOrFail((int) $studentRouteParameter);
        $studentId = $student->id;

        $rules = [
            // Personal Information
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar_url' => ['nullable', 'string', 'url', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'national_id' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('students')->ignore($studentId),
            ],
            // Current Address
            'current_address_line' => ['nullable', 'string', 'max:255'],
            'current_ward' => ['nullable', 'string', 'max:100'],
            'current_province' => ['nullable', 'string', 'max:100'],
            'current_country' => ['nullable', 'string', 'max:30'],
            // CCCD Address
            'cccd_address_line' => ['nullable', 'string', 'max:255'],
            'cccd_ward' => ['nullable', 'string', 'max:100'],
            'cccd_province' => ['nullable', 'string', 'max:100'],
            'cccd_country' => ['nullable', 'string', 'max:100'],
            // Emergency Contact
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_email' => ['nullable', 'email', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name_1' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone_1' => ['nullable', 'string', 'max:20'],
            'emergency_contact_email_1' => ['nullable', 'email', 'max:255'],
            'emergency_contact_relationship_1' => ['nullable', 'string', 'max:100'],
            // Academic Background
            'high_school_name' => ['nullable', 'string', 'max:255'],
            'high_school_graduation_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'entrance_exam_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'admission_notes' => ['nullable', 'string'],
            // Parent User
            'parent_name' => ['nullable', 'string', 'max:255'],
        ];

        $studentEmailRules = [
            'required',
            'string',
            'email',
            'max:255',
            Rule::unique('students')->ignore($studentId),
        ];

        if ($student->user_id) {
            $studentEmailRules[] = Rule::unique('users', 'email')->ignore($student->user_id);
        } else {
            $studentEmailRules[] = Rule::unique('users', 'email');
        }

        $rules['email'] = $studentEmailRules;

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
        $parentEmailRules[] = function ($attribute, $value, $fail) use ($student, $studentId) {
            if (empty($value)) {
                return; // Skip validation if empty (nullable)
            }

            $parentEmail = strtolower(trim($value));

            if ($parentEmail === strtolower(trim((string) $student->email))) {
                $fail('Sinh viên không thể tự gán chính mình làm phụ huynh.');

                return;
            }

            // Find user by email
            $user = User::where('email', $parentEmail)->first();

            if ($user) {
                if ($user->isStudent()) {
                    $fail('Không thể sử dụng email này làm phụ huynh. Email này thuộc về tài khoản sinh viên.');

                    return;
                }

                if ($user->isLecturer()) {
                    $fail('Không thể sử dụng email này làm phụ huynh. Email này thuộc về tài khoản giảng viên.');

                    return;
                }

                if ($user->isStaff()) {
                    $fail('Không thể sử dụng email này làm phụ huynh. Email này thuộc về tài khoản nhân viên.');

                    return;
                }

                if ($user->isService()) {
                    $fail('Không thể sử dụng email này làm phụ huynh. Email này thuộc về tài khoản dịch vụ.');

                    return;
                }

                if (app(GuardianAccessGrantReader::class)->hasRelationshipForOtherStudent((int) $user->id, (int) $studentId)) {
                    $fail('Email này đã được liên kết với sinh viên khác làm phụ huynh.');
                }
            }
        };

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
        // Only process fields that are allowed to be updated (displayed on UI)
        $stringFieldsToLowercase = [
            'email',
            'full_name',
            'nationality',
            'ethnicity',
            'gender',
            'current_address_line',
            'current_ward',
            'current_province',
            'current_country',
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
            'parent_email',
            'parent_name',
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
