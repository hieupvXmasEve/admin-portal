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

    /** The app ships no lang/ directory, so every rule needs its own sentence. */
    public function messages(): array
    {
        return [
            'on_behalf_note.required' => 'Hãy ghi rõ đã liên hệ sinh viên bằng cách nào và sinh viên đồng ý điều gì.',
            'on_behalf_note.min' => 'Hãy ghi nội dung thực chất, không ghi qua loa.',
            'on_behalf_note.max' => 'Ghi chú không được dài quá 2000 ký tự.',
        ];
    }
}
