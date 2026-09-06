<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Student360;

use Illuminate\Foundation\Http\FormRequest;

class PreviewManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
