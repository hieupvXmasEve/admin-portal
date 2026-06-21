<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestAiProviderSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('test_ai_provider_settings');
    }

    public function rules(): array
    {
        return [];
    }
}
