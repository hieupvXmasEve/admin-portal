<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AssessmentComponentDetailScore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateGradesRequest extends FormRequest
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
            'scores' => [
                'required',
                'array',
                'min:1',
                'max:100', // Reasonable limit for bulk operations
            ],
            'scores.*.id' => [
                'required',
                'integer',
                'exists:assessment_component_detail_scores,id',
            ],
            'scores.*.points_earned' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'scores.*.percentage_score' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],
            'scores.*.letter_grade' => [
                'sometimes',
                'string',
                'max:5',
                Rule::in(['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F', 'I', 'W', 'P', 'NP']),
            ],
            'scores.*.status' => [
                'sometimes',
                Rule::in(['not_submitted', 'submitted', 'grading', 'graded', 'returned']),
            ],
            'scores.*.score_status' => [
                'sometimes',
                Rule::in(['draft', 'provisional', 'final']),
            ],
            'scores.*.instructor_feedback' => [
                'sometimes',
                'string',
                'max:1000',
            ],
            'scores.*.private_notes' => [
                'sometimes',
                'string',
                'max:1000',
            ],
            'scores.*.bonus_points' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],
            'scores.*.bonus_reason' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'scores.*.score_excluded' => [
                'sometimes',
                'boolean',
            ],
            'scores.*.exclusion_reason' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'scores.*.late_excuse_approved' => [
                'sometimes',
                'boolean',
            ],
            'scores.*.plagiarism_suspected' => [
                'sometimes',
                'boolean',
            ],
            'scores.*.plagiarism_score' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],
            'scores.*.integrity_status' => [
                'sometimes',
                Rule::in(['clean', 'flagged', 'under_review', 'violation_confirmed', 'cleared']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'scores.required' => 'At least one score must be provided',
            'scores.array' => 'Scores must be provided as an array',
            'scores.min' => 'At least one score must be provided',
            'scores.max' => 'Cannot update more than 100 scores at once',
            'scores.*.id.required' => 'Score ID is required for each score',
            'scores.*.id.exists' => 'Invalid score ID provided',
            'scores.*.points_earned.min' => 'Points earned must be a positive number',
            'scores.*.percentage_score.min' => 'Percentage score must be between 0 and 100',
            'scores.*.percentage_score.max' => 'Percentage score must be between 0 and 100',
            'scores.*.letter_grade.in' => 'Invalid letter grade provided',
            'scores.*.status.in' => 'Invalid submission status',
            'scores.*.score_status.in' => 'Invalid score status',
            'scores.*.instructor_feedback.max' => 'Instructor feedback cannot exceed 1000 characters',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $courseOffering = $this->route('courseOffering');

            // Validate that all scores belong to the course offering
            if ($this->has('scores')) {
                $scoreIds = collect($this->scores)->pluck('id')->filter();

                $validScores = AssessmentComponentDetailScore::whereIn('id', $scoreIds)
                    ->where('course_offering_id', $courseOffering->id)
                    ->pluck('id');

                $invalidScores = $scoreIds->diff($validScores);

                if ($invalidScores->isNotEmpty()) {
                    $validator->errors()->add('scores', 'Some scores do not belong to this course offering: '.$invalidScores->implode(', '));
                }

                // Validate individual score constraints
                foreach ($this->scores as $index => $scoreData) {
                    if (isset($scoreData['id'])) {
                        $score = AssessmentComponentDetailScore::find($scoreData['id']);

                        if ($score && $score->assessmentComponentDetail) {
                            // Validate points_earned against max_points
                            if (isset($scoreData['points_earned']) && $score->assessmentComponentDetail->max_points) {
                                if ($scoreData['points_earned'] > $score->assessmentComponentDetail->max_points) {
                                    $validator->errors()->add(
                                        "scores.{$index}.points_earned",
                                        "Points earned cannot exceed maximum points ({$score->assessmentComponentDetail->max_points})"
                                    );
                                }
                            }

                            // Validate consistency between points and percentage
                            if (isset($scoreData['points_earned']) && isset($scoreData['percentage_score']) && $score->assessmentComponentDetail->max_points) {
                                $expectedPercentage = ($scoreData['points_earned'] / $score->assessmentComponentDetail->max_points) * 100;
                                if (abs($expectedPercentage - $scoreData['percentage_score']) > 0.01) {
                                    $validator->errors()->add(
                                        "scores.{$index}.percentage_score",
                                        'Percentage score must be consistent with points earned'
                                    );
                                }
                            }

                            // Validate required fields for final scores
                            if (isset($scoreData['score_status']) && $scoreData['score_status'] === 'final') {
                                if (! isset($scoreData['points_earned']) && ! isset($scoreData['percentage_score'])) {
                                    $validator->errors()->add(
                                        "scores.{$index}.score_status",
                                        'Final scores must have either points earned or percentage score'
                                    );
                                }
                            }

                            // Validate bonus reason when bonus points are provided
                            if (isset($scoreData['bonus_points']) && $scoreData['bonus_points'] > 0 && empty($scoreData['bonus_reason'])) {
                                $validator->errors()->add(
                                    "scores.{$index}.bonus_reason",
                                    'Bonus reason is required when awarding bonus points'
                                );
                            }

                            // Validate exclusion reason when score is excluded
                            if (isset($scoreData['score_excluded']) && $scoreData['score_excluded'] && empty($scoreData['exclusion_reason'])) {
                                $validator->errors()->add(
                                    "scores.{$index}.exclusion_reason",
                                    'Exclusion reason is required when excluding a score'
                                );
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Get the validated data with additional processing.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Add metadata to each score
        $lecturer = $this->user();
        $now = now();

        foreach ($validated['scores'] as &$scoreData) {
            $scoreData['graded_by_lecture_id'] = $lecturer->id;
            $scoreData['graded_at'] = $now;
            $scoreData['last_modified_by_lecture_id'] = $lecturer->id;
            $scoreData['last_modified_at'] = $now;
        }

        return $validated;
    }
}
