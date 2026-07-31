<?php

declare(strict_types=1);

namespace App\Http\Requests\ScholarshipAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class DecideScholarshipAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide_scholarship_adjustment')
            && $this->user()->can('decide', $this->route('dossier'));
    }

    public function rules(): array
    {
        return [
            'decision_type' => ['required', 'string', 'in:keep,reduce,suspend_full,defer,cancel'],
            'adjusted_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'],
            'exception_override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
