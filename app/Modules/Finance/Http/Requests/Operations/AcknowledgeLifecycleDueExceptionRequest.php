<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeLifecycleDueExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_finance_operations_due_calendar') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:2000',
        ];
    }
}
