<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_finance_settings') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'credit_offset_enabled' => ['required', 'boolean'],
            'credit_offset_min_balance' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];
    }
}
