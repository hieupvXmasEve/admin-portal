<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAggregateConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('configure_survey_aggregate');
    }

    public function rules(): array
    {
        return [
            'overall' => ['required', 'array'],
            'overall.question_codes' => ['required', 'array', 'min:1'],
            'overall.question_codes.*' => ['string'],
            'overall.thresholds' => ['required', 'array'],
            'overall.thresholds.positive_min' => ['required', 'integer', 'between:1,5'],
            'overall.thresholds.negative_max' => ['required', 'integer', 'between:1,5'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $positiveMin = (int) $this->input('overall.thresholds.positive_min');
            $negativeMax = (int) $this->input('overall.thresholds.negative_max');

            if ($positiveMin <= $negativeMax) {
                $validator->errors()->add(
                    'overall.thresholds.positive_min',
                    'Positive threshold must be greater than the negative threshold.'
                );
            }

            $this->validateQuestionCodes($validator);
        });
    }

    private function validateQuestionCodes(Validator $validator): void
    {
        $codes = $this->input('overall.question_codes', []);
        if (! is_array($codes) || $codes === []) {
            return;
        }

        /** @var \App\Modules\Engagement\Models\Form $form */
        $form = $this->route('form');
        $version = $form->latestPublishedVersion()->first();

        if (! $version) {
            $validator->errors()->add('overall.question_codes', 'Form has no published version to configure.');

            return;
        }

        $ratingCodes = $version->questions()
            ->where('type', 'rating')
            ->pluck('code')
            ->all();

        foreach ($codes as $code) {
            if (! in_array($code, $ratingCodes, true)) {
                $validator->errors()->add('overall.question_codes', "Question code \"{$code}\" is not a rating question in the current published version.");
            }
        }
    }
}
