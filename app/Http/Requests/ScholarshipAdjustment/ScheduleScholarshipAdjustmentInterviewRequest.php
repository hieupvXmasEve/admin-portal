<?php

declare(strict_types=1);

namespace App\Http\Requests\ScholarshipAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleScholarshipAdjustmentInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_scholarship_interview')
            && $this->user()->can('manageInterview', $this->route('dossier'));
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'mode' => ['required', 'string', 'in:in_person,online'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
