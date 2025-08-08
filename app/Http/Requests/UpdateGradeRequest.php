<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGradeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'points_earned' => [
                'sometimes',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    // Validate against max_points if available
                    $score = $this->route('score');
                    if ($score && $score->assessmentComponentDetail && $score->assessmentComponentDetail->max_points) {
                        if ($value > $score->assessmentComponentDetail->max_points) {
                            $fail("Points earned cannot exceed maximum points ({$score->assessmentComponentDetail->max_points})");
                        }
                    }
                },
            ],
            'percentage_score' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],
            'letter_grade' => [
                'sometimes',
                'string',
                'max:5',
                Rule::in(['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F', 'I', 'W', 'P', 'NP']),
            ],
            'status' => [
                'sometimes',
                Rule::in(['not_submitted', 'submitted', 'grading', 'graded', 'returned']),
            ],
            'score_status' => [
                'sometimes',
                Rule::in(['draft', 'provisional', 'final']),
            ],
            'instructor_feedback' => [
                'sometimes',
                'string',
                'max:1000',
            ],
            'private_notes' => [
                'sometimes',
                'string',
                'max:1000',
            ],
            'bonus_points' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100', // Reasonable limit for bonus points
            ],
            'bonus_reason' => [
                'sometimes',
                'string',
                'max:255',
                'required_with:bonus_points',
            ],
            'score_excluded' => [
                'sometimes',
                'boolean',
            ],
            'exclusion_reason' => [
                'sometimes',
                'string',
                'max:255',
                'required_if:score_excluded,true',
            ],
            'late_excuse_approved' => [
                'sometimes',
                'boolean',
            ],
            'late_excuse_reason' => [
                'sometimes',
                'string',
                'max:500',
            ],
            'plagiarism_suspected' => [
                'sometimes',
                'boolean',
            ],
            'plagiarism_score' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
                'required_if:plagiarism_suspected,true',
            ],
            'plagiarism_notes' => [
                'sometimes',
                'string',
                'max:1000',
            ],
            'integrity_status' => [
                'sometimes',
                Rule::in(['clean', 'flagged', 'under_review', 'violation_confirmed', 'cleared']),
            ],
            'appeal_requested' => [
                'sometimes',
                'boolean',
            ],
            'appeal_reason' => [
                'sometimes',
                'string',
                'max:500',
                'required_if:appeal_requested,true',
            ],
            'appeal_status' => [
                'sometimes',
                Rule::in(['pending', 'under_review', 'approved', 'denied']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'points_earned.min' => 'Points earned must be a positive number',
            'percentage_score.min' => 'Percentage score must be between 0 and 100',
            'percentage_score.max' => 'Percentage score must be between 0 and 100',
            'letter_grade.in' => 'Invalid letter grade provided',
            'status.in' => 'Invalid submission status',
            'score_status.in' => 'Invalid score status',
            'instructor_feedback.max' => 'Instructor feedback cannot exceed 1000 characters',
            'bonus_reason.required_with' => 'Bonus reason is required when awarding bonus points',
            'exclusion_reason.required_if' => 'Exclusion reason is required when excluding a score',
            'plagiarism_score.required_if' => 'Plagiarism score is required when flagging for plagiarism',
            'appeal_reason.required_if' => 'Appeal reason is required when requesting an appeal',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure percentage_score and points_earned are consistent if both provided
            if ($this->has('points_earned') && $this->has('percentage_score')) {
                $score = $this->route('score');
                if ($score && $score->assessmentComponentDetail && $score->assessmentComponentDetail->max_points) {
                    $expectedPercentage = ($this->points_earned / $score->assessmentComponentDetail->max_points) * 100;
                    if (abs($expectedPercentage - $this->percentage_score) > 0.01) {
                        $validator->errors()->add('percentage_score', 'Percentage score must be consistent with points earned');
                    }
                }
            }

            // Validate that final scores have required fields
            if ($this->score_status === 'final') {
                if (! $this->has('points_earned') && ! $this->has('percentage_score')) {
                    $validator->errors()->add('score_status', 'Final scores must have either points earned or percentage score');
                }
            }
        });
    }
}
