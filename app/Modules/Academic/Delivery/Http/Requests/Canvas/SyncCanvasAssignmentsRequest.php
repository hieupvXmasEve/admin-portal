<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Canvas;

use Illuminate\Foundation\Http\FormRequest;

class SyncCanvasAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_group_ids' => ['nullable', 'array'],
            'selected_group_ids.*' => ['string', 'max:255'],
        ];
    }
}
