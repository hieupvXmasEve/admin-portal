<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Canvas;

use Illuminate\Foundation\Http\FormRequest;

class CanvasIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'canvas_url' => ['required', 'url', 'max:255'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'canvas_url.required' => 'Canvas URL is required',
            'canvas_url.url' => 'Canvas URL must be a valid URL',
            'canvas_url.max' => 'Canvas URL cannot exceed 255 characters',
            'client_id.required' => 'Client ID is required',
            'client_id.max' => 'Client ID cannot exceed 255 characters',
            'client_secret.required' => 'Client Secret is required',
            'client_secret.max' => 'Client Secret cannot exceed 1000 characters',
        ];
    }
}
