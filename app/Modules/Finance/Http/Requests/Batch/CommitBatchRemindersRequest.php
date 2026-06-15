<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class CommitBatchRemindersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_finance_operations_due_calendar');
    }

    /**
     * recipient comes from the trusted token scope (not the client).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preview_token' => 'required|string',
            'selected_keys' => 'required|array|min:1',
            'selected_keys.*' => 'required|string',
            'force_resend' => 'nullable|boolean',
        ];
    }
}