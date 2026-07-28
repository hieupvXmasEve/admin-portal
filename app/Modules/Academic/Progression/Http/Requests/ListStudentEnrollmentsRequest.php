<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListStudentEnrollmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'semester_id' => 'nullable|exists:semesters,id',
            'search' => 'nullable|string|max:255',
        ];
    }
}
