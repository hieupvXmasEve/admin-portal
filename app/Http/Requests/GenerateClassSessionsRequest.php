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
        $courseOffering = $this->route('courseOffering');
        $semester = $courseOffering?->semester;

        $after = $semester?->start_date?->toDateString();
        $before = $semester?->end_date?->toDateString();

        $rules = [
            'room_id' => [
                'required',
                'integer',
                Rule::exists('rooms', 'id')->where(function ($query) {
                    return $query->where('is_bookable', true)
                        ->where('status', Room::STATUS_AVAILABLE);
                }),
            ],
            'start_date' => [
                'required',
                'date',
            ],
        ];

        // Enforce semester window if available
        if ($after) {
            $rules['start_date'][] = 'after_or_equal:' . $after;
        }
        if ($before) {
            $rules['start_date'][] = 'before_or_equal:' . $before;
        }

        return $rules;
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'A room must be selected to generate class sessions.',
            'room_id.exists' => 'The selected room is not available for booking.',
            'start_date.required' => 'Please select a start date for the sessions.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.after_or_equal' => 'Start date must be on or after the semester start date.',
            'start_date.before_or_equal' => 'Start date must be on or before the semester end date.',
        ];
    }
}
