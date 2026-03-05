<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SendManualNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('send_manual_notification');
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'notifiable_type' => ['required', 'string', 'in:student,user,lecturer'],
            'notifiable_ids' => ['required', 'array', 'min:1'],
            'notifiable_ids.*' => ['integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'category' => ['required', new Enum(NotificationCategory::class)],
            'is_important' => ['boolean'],
            'action_url' => ['nullable', 'string', 'max:500'],
            'action_text' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('campus_id')) {
            $this->merge([
                'campus_id' => session('current_campus_id'),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campus_id.required' => 'Campus context is required for sending notifications.',
            'campus_id.exists' => 'Invalid campus selected.',
        ];
    }
}
