<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCourseRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|Rule>> */
    public function rules(): array
    {
        return [
            'registration_status' => ['required', 'string', Rule::in(['registered', 'confirmed', 'dropped', 'withdrawn', 'completed', 'defer'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
