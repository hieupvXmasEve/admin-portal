<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\RetakeCourse;

use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tạo phí học lại trực tiếp — không tạo DNG payment request.
 * Dùng cho trang /finance/retake-course khi admin chỉ muốn ghi nhận
 * khoản phí nội bộ mà không đẩy sang hệ thống DNG.
 */
class StoreRetakeCourseChargeSimpleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_id' => ['required', 'integer', 'exists:course_retake_registrations,id'],
            'charge_type' => ['required', 'string', Rule::in([
                FinanceCharge::TYPE_RETAKE_FEE,
                FinanceCharge::TYPE_EXAM_RESIT_FEE,
                FinanceCharge::TYPE_MANUAL_FEE,
            ])],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_id.required' => 'Đăng ký học lại là bắt buộc.',
            'registration_id.exists' => 'Đăng ký học lại không tồn tại.',
            'charge_type.required' => 'Loại phí là bắt buộc.',
            'charge_type.in' => 'Loại phí không hợp lệ.',
            'amount.required' => 'Số tiền là bắt buộc.',
            'amount.min' => 'Số tiền phải lớn hơn hoặc bằng 0.',
            'description.required' => 'Mô tả khoản phí là bắt buộc.',
            'description.max' => 'Mô tả không được vượt quá 255 ký tự.',
        ];
    }
}
