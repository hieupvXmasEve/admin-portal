<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class MarkExamResitNoShowRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.string' => 'Lý do ghi nhận không hợp lệ.',
            'reason.max' => 'Lý do ghi nhận tối đa 1000 ký tự.',
        ];
    }
}
