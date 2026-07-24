<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitStudentSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'exists:questions,id'],
        ];
    }
}
