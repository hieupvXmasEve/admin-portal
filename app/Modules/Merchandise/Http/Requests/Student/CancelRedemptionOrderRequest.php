<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class CancelRedemptionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
