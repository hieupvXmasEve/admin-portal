<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\RetakeCourse;

use Illuminate\Foundation\Http\FormRequest;

class StoreRetakeCourseChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_id' => ['required', 'integer', 'exists:course_retake_registrations,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_deadline' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_id.required' => 'Đăng ký học lại là bắt buộc.',
            'payment_deadline.required' => 'Hạn thanh toán là bắt buộc.',
            'payment_deadline.after_or_equal' => 'Hạn thanh toán phải từ hôm nay trở đi.',
        ];
    }
}
