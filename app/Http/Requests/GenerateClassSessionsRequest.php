<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateClassSessionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'room_id' => [
                'required',
                'integer',
                Rule::exists('rooms', 'id')->where(function ($query) {
                    return $query->where('is_bookable', true)
                        ->where('status', Room::STATUS_AVAILABLE);
                }),
            ],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'A room must be selected to generate class sessions.',
            'room_id.exists' => 'The selected room is not available for booking.',
        ];
    }
}
