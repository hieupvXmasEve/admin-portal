<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

final class ExportBatchChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create_finance_charges')
            || $this->user()?->can('generate_egc_finance_charges'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preview_token' => 'required|string',
        ];
    }
}
