<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Form;
use App\Models\Question;

class SubmitFormResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if authenticated as student
        return auth('student')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $form = Form::findOrFail($this->route('form'));
        $version = $form->latestPublishedVersion;
        
        $rules = [
            'target_scope_type' => 'nullable|in:section,class_session,course,global',
            'target_scope_id' => 'nullable|integer',
            'anonymized' => 'boolean',
            'origin' => 'nullable|in:web,mobile,api',
            'answers' => 'required|array',
        ];

        // Add validation rules for each question
        if ($version) {
            foreach ($version->questions as $question) {
                $questionRules = $this->getQuestionRules($question);
                if ($questionRules) {
                    $rules["answers.{$question->id}"] = $questionRules;
                }
            }
        }

        // If it's a query form, add query-specific rules
        if ($form->type === 'query') {
            $rules['topic_id'] = 'nullable|exists:query_topics,id';
            $rules['custom_topic_text'] = 'nullable|string|max:255|required_without:topic_id';
            $rules['priority'] = 'nullable|in:low,normal,high';
        }

        return $rules;
    }

    /**
     * Get validation rules for a specific question.
     */
    protected function getQuestionRules(Question $question): string
    {
        $rules = [];

        // Base requirement
        if ($question->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Type-specific rules
        switch ($question->type) {
            case 'short_text':
                $rules[] = 'string';
                if ($question->validation_json && isset($question->validation_json['maxLength'])) {
                    $rules[] = 'max:' . $question->validation_json['maxLength'];
                }
                break;

            case 'long_text':
                $rules[] = 'string';
                if ($question->validation_json && isset($question->validation_json['maxLength'])) {
                    $rules[] = 'max:' . $question->validation_json['maxLength'];
                }
                break;

            case 'number':
                $rules[] = 'numeric';
                if ($question->validation_json) {
                    if (isset($question->validation_json['min'])) {
                        $rules[] = 'min:' . $question->validation_json['min'];
                    }
                    if (isset($question->validation_json['max'])) {
                        $rules[] = 'max:' . $question->validation_json['max'];
                    }
                }
                break;

            case 'rating':
                $rules[] = 'integer';
                if ($question->validation_json) {
                    if (isset($question->validation_json['min'])) {
                        $rules[] = 'min:' . $question->validation_json['min'];
                    }
                    if (isset($question->validation_json['max'])) {
                        $rules[] = 'max:' . $question->validation_json['max'];
                    }
                }
                break;

            case 'date':
                $rules[] = 'date';
                break;

            case 'single_choice':
                $optionIds = $question->options->pluck('id')->toArray();
                $rules[] = 'array';
                $rules[] = 'size:1';
                break;

            case 'multi_choice':
                $optionIds = $question->options->pluck('id')->toArray();
                $rules[] = 'array';
                break;

            case 'likert':
                $optionIds = $question->options->pluck('id')->toArray();
                $rules[] = 'array';
                $rules[] = 'size:1';
                break;

            case 'yes_no':
                $rules[] = 'boolean';
                break;

            case 'file':
                $rules[] = 'file';
                if ($question->validation_json) {
                    if (isset($question->validation_json['maxSize'])) {
                        $rules[] = 'max:' . $question->validation_json['maxSize'];
                    }
                    if (isset($question->validation_json['mimes'])) {
                        $rules[] = 'mimes:' . implode(',', $question->validation_json['mimes']);
                    }
                }
                break;

            case 'matrix':
                $rules[] = 'array';
                break;
        }

        return implode('|', $rules);
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        $messages = [
            'answers.required' => 'Please answer all required questions.',
            'custom_topic_text.required_without' => 'Please specify the topic when selecting "Other".',
        ];

        $form = Form::findOrFail($this->route('form'));
        $version = $form->latestPublishedVersion;

        if ($version) {
            foreach ($version->questions as $question) {
                $messages["answers.{$question->id}.required"] = "The question \"{$question->text}\" is required.";
                $messages["answers.{$question->id}.max"] = "The answer for \"{$question->text}\" exceeds the maximum allowed length.";
                $messages["answers.{$question->id}.min"] = "The answer for \"{$question->text}\" is below the minimum value.";
            }
        }

        return $messages;
    }
}