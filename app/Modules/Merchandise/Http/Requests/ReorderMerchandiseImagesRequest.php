<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMerchandiseImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_merchandise_image');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'distinct', 'exists:merchandise_images,id'],
        ];
    }
}
