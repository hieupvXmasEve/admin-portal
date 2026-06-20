<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class CancelExamResitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Lý do hủy là bắt buộc.',
            'reason.min' => 'Lý do hủy phải có ít nhất 5 ký tự.',
        ];
    }
}
