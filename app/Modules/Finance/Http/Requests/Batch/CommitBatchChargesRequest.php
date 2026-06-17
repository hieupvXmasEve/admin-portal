<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class CommitBatchChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('create_finance_charges')
            || $this->user()?->can('generate_egc_finance_charges'));
    }

    /**
     * Note: fee_category + semester_id are NOT accepted from the client — they are read
     * from the trusted token scope at commit time (tamper-proof). The client sends only
     * the token and the line keys it confirmed.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preview_token' => 'required|string',
            'selected_keys' => 'required|array|min:1',
            'selected_keys.*' => 'required|string',
            'block_overrides' => 'nullable|array',
            'block_overrides.*' => 'integer|in:1,2',
            'acknowledged' => 'nullable|boolean',
        ];
    }
}
