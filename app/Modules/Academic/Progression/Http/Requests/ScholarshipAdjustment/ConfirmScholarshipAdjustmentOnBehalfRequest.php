<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmScholarshipAdjustmentOnBehalfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('confirm_scholarship_adjustment_on_behalf')
            && $this->user()->can('confirmOnBehalf', $this->route('dossier'));
    }

    public function rules(): array
    {
        return [
            // Mandatory contact-evidence note — an on-behalf acknowledgement
            // must record how/why the student's agreement was obtained offline.
            'on_behalf_note' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
