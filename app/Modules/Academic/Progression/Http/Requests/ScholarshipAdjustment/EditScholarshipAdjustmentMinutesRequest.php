<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class EditScholarshipAdjustmentMinutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage_scholarship_interview')
            && $this->user()->can('manageInterview', $this->route('dossier'));
    }

    public function rules(): array
    {
        return [
            'minutes' => ['required', 'string', 'max:10000'],
        ];
    }

    /** The app ships no lang/ directory, so every rule needs its own sentence. */
    public function messages(): array
    {
        return [
            'minutes.required' => 'Hãy nhập nội dung biên bản đã chỉnh sửa.',
            'minutes.max' => 'Biên bản không được dài quá 10000 ký tự.',
        ];
    }
}
