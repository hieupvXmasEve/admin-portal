<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Dng;

use Illuminate\Foundation\Http\FormRequest;

class ResolveDngReservationOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', 'in:pushed,restored_pending,cancelled,failed'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
