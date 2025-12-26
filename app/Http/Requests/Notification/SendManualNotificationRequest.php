<?php

namespace App\Http\Requests\Notification;

use App\Enums\NotificationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SendManualNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('send_manual_notification');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notifiable_type' => ['required', 'string', 'in:student,user,lecturer'],
            'notifiable_ids' => ['required', 'array', 'min:1'],
            'notifiable_ids.*' => ['integer'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'category' => ['required', new Enum(NotificationCategory::class)],
            'is_important' => ['boolean'],
            'action_url' => ['nullable', 'string', 'max:500'],
            'action_text' => ['nullable', 'string', 'max:50'],
        ];
    }
}
