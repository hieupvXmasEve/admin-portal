<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertCampusPeriodScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'integer', 'min:1'],
            'operating_start_date' => ['nullable', 'date', 'required_with:operating_end_date'],
            'operating_end_date' => ['nullable', 'date', 'required_with:operating_start_date', 'after_or_equal:operating_start_date'],
            'registration_start_date' => ['nullable', 'date', 'required_with:registration_end_date'],
            'registration_end_date' => ['nullable', 'date', 'required_with:registration_start_date', 'after_or_equal:registration_start_date'],
        ];
    }
}
