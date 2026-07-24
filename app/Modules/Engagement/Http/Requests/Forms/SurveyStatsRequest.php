<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

final class SurveyStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'string'],
        ];
    }
}
