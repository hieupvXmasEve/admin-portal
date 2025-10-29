<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class ChangeStudentStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $status = $this->input('status');

        return [
            'status' => 'required|string|in:active,inactive,suspended,graduated,intake_pre_uni_gc,intake_course,deferred,dropout,dropout_transfer,pending',
            'gc_current_level' => $status === 'intake_pre_uni_gc' ? 'required|integer|min:0|max:6' : 'nullable|integer|min:0|max:6',
            'gc_starting_level' => $status === 'intake_pre_uni_gc' ? 'required|integer|min:0|max:6' : 'nullable|integer|min:0|max:6',
            'gc_total_levels' => 'nullable|integer|min:1|max:6',
            'reason' => 'required|string|min:10|max:500',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $student = $this->route('student');
            $gcCurrentLevel = $this->input('gc_current_level');
            $gcStartingLevel = $this->input('gc_starting_level');
            $gcTotalLevels = $this->input('gc_total_levels') ?? 6;
            $status = $this->input('status');

            // If status is intake_pre_uni_gc, validate GC fields
            if ($status === 'intake_pre_uni_gc') {
                // Validate gc_current_level doesn't exceed gc_total_levels
                if ($gcCurrentLevel !== null && $gcCurrentLevel > $gcTotalLevels) {
                    $validator->errors()->add(
                        'gc_current_level',
                        "GC current level cannot exceed {$gcTotalLevels}"
                    );
                }

                // Validate gc_starting_level doesn't exceed gc_total_levels
                if ($gcStartingLevel !== null && $gcStartingLevel > $gcTotalLevels) {
                    $validator->errors()->add(
                        'gc_starting_level',
                        "GC starting level cannot exceed {$gcTotalLevels}"
                    );
                }

                // Validate gc_current_level >= gc_starting_level
                if ($gcCurrentLevel !== null && $gcStartingLevel !== null && $gcCurrentLevel < $gcStartingLevel) {
                    $validator->errors()->add(
                        'gc_current_level',
                        'GC current level cannot be less than GC starting level'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.in' => 'Invalid status value',
            'gc_current_level.required' => 'GC current level is required when status is Intake Pre-Uni GC',
            'gc_current_level.integer' => 'GC current level must be a number',
            'gc_current_level.min' => 'GC current level must be at least 0',
            'gc_current_level.max' => 'GC current level cannot exceed 6',
            'gc_starting_level.required' => 'GC starting level is required when status is Intake Pre-Uni GC',
            'gc_starting_level.integer' => 'GC starting level must be a number',
            'gc_starting_level.min' => 'GC starting level must be at least 0',
            'gc_starting_level.max' => 'GC starting level cannot exceed 6',
            'gc_total_levels.integer' => 'GC total levels must be a number',
            'gc_total_levels.min' => 'GC total levels must be at least 1',
            'gc_total_levels.max' => 'GC total levels cannot exceed 6',
            'reason.required' => 'Reason is required',
            'reason.min' => 'Reason must be at least 10 characters',
            'reason.max' => 'Reason must not exceed 500 characters',
        ];
    }
}
