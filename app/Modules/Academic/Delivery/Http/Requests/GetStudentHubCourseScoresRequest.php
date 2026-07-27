<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetStudentHubCourseScoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
