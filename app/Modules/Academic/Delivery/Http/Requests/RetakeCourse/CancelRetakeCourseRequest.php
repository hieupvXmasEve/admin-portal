<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\RetakeCourse;

use Illuminate\Foundation\Http\FormRequest;

class CancelRetakeCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Lý do hủy là bắt buộc.',
            'reason.min' => 'Lý do hủy phải có ít nhất 5 ký tự.',
        ];
    }
}
