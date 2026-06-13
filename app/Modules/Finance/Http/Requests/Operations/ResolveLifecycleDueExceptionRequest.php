<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class ResolveLifecycleDueExceptionRequest extends FormRequest
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
            'resolution_action' => 'required|string|in:acknowledge,keep_as_debt,route_to_settlement,cancel_dng,cancel_dng_and_void_linked_charge',
            'reason' => 'required|string|min:3|max:2000',
        ];
    }
}
