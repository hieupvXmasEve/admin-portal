<?php

declare(strict_types=1);

namespace App\Http\Requests\ScholarshipAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class CompleteScholarshipAdjustmentInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_scholarship_interview')
            && $this->user()->can('manageInterview', $this->route('dossier'));
    }

    public function rules(): array
    {
        return [
            'minutes' => ['required', 'string'],
            'participants' => ['nullable', 'array'],
        ];
    }
}
