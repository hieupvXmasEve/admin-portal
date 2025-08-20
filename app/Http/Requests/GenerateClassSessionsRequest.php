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
            'weekly_schedule' => [
                'required',
                'array',
            ],
            'weekly_schedule.monday' => $this->getDayValidationRules(),
            'weekly_schedule.tuesday' => $this->getDayValidationRules(),
            'weekly_schedule.wednesday' => $this->getDayValidationRules(),
            'weekly_schedule.thursday' => $this->getDayValidationRules(),
            'weekly_schedule.friday' => $this->getDayValidationRules(),
            'weekly_schedule.saturday' => $this->getDayValidationRules(),
            'weekly_schedule.sunday' => $this->getDayValidationRules(),
        ];

        // Add custom validation to ensure at least one day is enabled
        $rules['weekly_schedule'][] = function ($attribute, $value, $fail) {
            $hasEnabledDay = false;
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                if (isset($value[$day]['enabled']) && $value[$day]['enabled'] === true) {
                    $hasEnabledDay = true;
                    break;
                }
            }
            if (!$hasEnabledDay) {
                $fail('At least one day must be enabled in the weekly schedule.');
            }
        };

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
     * Get validation rules for individual day schedule
     */
    private function getDayValidationRules(): array
    {
        return [
            'required',
            'array',
            function ($attribute, $value, $fail) {
                if (!isset($value['enabled'])) {
                    $fail("The {$attribute}.enabled field is required.");
                }
                if (!is_bool($value['enabled'])) {
                    $fail("The {$attribute}.enabled field must be a boolean.");
                }
                if ($value['enabled']) {
                    if (!isset($value['startTime']) || !isset($value['endTime'])) {
                        $fail("The {$attribute} must have startTime and endTime when enabled.");
                    }
                    if (isset($value['startTime'], $value['endTime'])) {
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value['startTime'])) {
                            $fail("The {$attribute}.startTime must be in HH:MM format.");
                        }
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value['endTime'])) {
                            $fail("The {$attribute}.endTime must be in HH:MM format.");
                        }
                        if (strtotime($value['startTime']) >= strtotime($value['endTime'])) {
                            $fail("The {$attribute}.endTime must be after startTime.");
                        }
                    }
                }
            }
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
            'start_date.required' => 'Please select a start date for the sessions.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.after_or_equal' => 'Start date must be on or after the semester start date.',
            'start_date.before_or_equal' => 'Start date must be on or before the semester end date.',
        ];
    }
}
