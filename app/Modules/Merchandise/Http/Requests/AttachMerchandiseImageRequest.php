<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachMerchandiseImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_merchandise_image');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'max:5120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
