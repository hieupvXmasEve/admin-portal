<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class OverruleScholarshipAdjustmentDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve_scholarship_adjustment')
            && $this->user()->can('overruleDispute', $this->route('dossier'));
    }

    public function rules(): array
    {
        return [
            // The reason is the whole point of this action: it is what the
            // record shows in place of an agreement the student never gave.
            'overrule_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'overrule_reason.required' => 'Hãy nêu lý do bác bỏ phản đối của sinh viên — nội dung được lưu vào hồ sơ.',
            'overrule_reason.min' => 'Hãy ghi lý do thực chất, không ghi qua loa.',
            'overrule_reason.max' => 'Lý do không được dài quá 1000 ký tự.',
        ];
    }
}
