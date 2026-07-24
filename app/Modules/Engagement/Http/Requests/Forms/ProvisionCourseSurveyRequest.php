<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProvisionCourseSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form_id' => [
                'required',
                'integer',
                Rule::exists('forms', 'id')->where('type', 'survey'),
            ],
        ];
    }
}
