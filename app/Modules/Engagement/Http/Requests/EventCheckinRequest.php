<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventCheckinRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // return $this->user() && $this->user()->can('events.checkin');
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'event_id' => 'required|exists:events,id',
            'qr_code' => 'required|string|max:255',
            'force_register' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'event_id.required' => 'Event is required.',
            'event_id.exists' => 'Selected event does not exist.',
            'qr_code.required' => 'QR code is required.',
            'qr_code.string' => 'QR code must be a valid string.',
            'qr_code.max' => 'QR code cannot exceed 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'event_id' => 'event',
            'qr_code' => 'QR code',
            'force_register' => 'force registration',
        ];
    }
}
