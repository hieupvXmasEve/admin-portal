<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClassSessionAttendanceFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ];
    }
}
