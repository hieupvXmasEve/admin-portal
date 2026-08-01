<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Requests;

use App\Models\Merchandise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMerchandiseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create_merchandise');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'gold_price' => ['required', 'integer', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(Merchandise::STATUSES)],
        ];
    }
}
