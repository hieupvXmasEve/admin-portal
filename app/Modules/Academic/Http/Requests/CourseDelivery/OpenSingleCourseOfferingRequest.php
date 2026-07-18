<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\CourseDelivery;

use Illuminate\Foundation\Http\FormRequest;

final class OpenSingleCourseOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'lecture_id' => ['nullable', 'integer'],
            'section_code' => ['nullable', 'string', 'max:10'],
            'max_capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'waitlist_capacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'delivery_mode' => ['required', 'in:in_person,online,hybrid,blended'],
            'schedule_days' => ['nullable', 'array'],
            'schedule_days.*' => ['string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'schedule_time_start' => ['nullable', 'date_format:H:i'],
            'schedule_time_end' => ['nullable', 'date_format:H:i', 'after:schedule_time_start'],
            'location' => ['nullable', 'string', 'max:255'],
            'special_requirements' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
