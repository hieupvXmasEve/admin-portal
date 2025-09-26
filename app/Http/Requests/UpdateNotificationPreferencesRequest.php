<?php

namespace App\Http\Requests;

use App\Models\UserEmailPreference;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $eventTypes = [
            UserEmailPreference::TYPE_EVENT_PUBLICATION,
            UserEmailPreference::TYPE_EVENT_REGISTRATION,
            UserEmailPreference::TYPE_EVENT_CHECKIN,
            UserEmailPreference::TYPE_EVENT_GOLD_REWARD,
            UserEmailPreference::TYPE_EVENT_CANCELLATION,
            UserEmailPreference::TYPE_EVENT_UPDATE,
        ];

        $frequencies = array_keys(UserEmailPreference::getFrequencies());

        $rules = [
            'preferences' => 'required|array',
        ];

        foreach ($eventTypes as $type) {
            $rules["preferences.{$type}"] = 'sometimes|array';
            $rules["preferences.{$type}.enabled"] = 'required_with:preferences.' . $type . '|boolean';
            $rules["preferences.{$type}.frequency"] = 'required_with:preferences.' . $type . '|in:' . implode(',', $frequencies);
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'preferences.required' => 'Notification preferences are required.',
            'preferences.array' => 'Notification preferences must be an array.',
            'preferences.*.enabled.boolean' => 'Notification enabled status must be true or false.',
            'preferences.*.frequency.in' => 'Invalid notification frequency selected.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        $notificationTypes = UserEmailPreference::getNotificationTypes();
        $attributes = [];

        foreach ($notificationTypes as $type => $label) {
            $attributes["preferences.{$type}.enabled"] = $label . ' enabled status';
            $attributes["preferences.{$type}.frequency"] = $label . ' frequency';
        }

        return $attributes;
    }
}
