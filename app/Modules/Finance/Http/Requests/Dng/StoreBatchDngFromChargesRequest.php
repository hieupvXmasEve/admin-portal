<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Dng;

use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use Illuminate\Foundation\Http\FormRequest;

class StoreBatchDngFromChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route-level gate handles authorization
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_ids' => 'required|array|min:1|max:100',
            'student_ids.*' => 'integer|exists:students,id',
            'dng_fee_type' => 'required|string|in:'.implode(',', DngFeeTypeOptions::values()),
            'due_date' => 'required|date|after_or_equal:today',
            'semester_id' => 'required|integer|exists:semesters,id',
            'description' => 'required|string|max:255',
            'estimate_time' => 'required|string|max:10',
            'amount_overrides' => 'nullable|array',
            'amount_overrides.*' => 'numeric|min:1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_ids.required' => 'Vui lòng chọn ít nhất 1 sinh viên.',
            'student_ids.min' => 'Vui lòng chọn ít nhất 1 sinh viên.',
            'student_ids.max' => 'Tối đa 100 sinh viên mỗi lần.',
            'dng_fee_type.required' => 'Vui lòng chọn loại phí DNG.',
            'dng_fee_type.in' => 'Loại phí DNG không hợp lệ.',
            'due_date.required' => 'Vui lòng chọn hạn thanh toán.',
            'due_date.after_or_equal' => 'Hạn thanh toán phải từ hôm nay trở đi.',
            'semester_id.required' => 'Vui lòng chọn kỳ học.',
            'description.required' => 'Vui lòng nhập mô tả khoản phí.',
            'estimate_time.required' => 'Vui lòng nhập thời hạn thanh toán (MM/YY).',
        ];
    }
}
