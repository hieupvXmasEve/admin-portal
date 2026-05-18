<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Modules\Finance\Enums\NonAcademicChargeTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * FormRequest for the non-academic charge generation endpoint.
 *
 * Authorization (F2): permission gate lives here, NOT on the route middleware.
 * Returns false when user lacks view_finance_operations_generate_charges.
 */
class GenerateNonAcademicChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('view_finance_operations_generate_charges') ?? false;
    }

    public function rules(): array
    {
        return [
            'fee_type' => ['required', 'string', Rule::enum(NonAcademicChargeTypeEnum::class)],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'csv_file' => [
                'required',
                // File::types() validates extension; mimetypes rule guards against MIME sniffing.
                File::types(['csv', 'txt'])->max(1024),
                'mimetypes:text/csv,text/plain,application/csv',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'fee_type.required' => 'Vui lòng chọn loại phí.',
            'fee_type.enum' => 'Loại phí không hợp lệ.',
            'semester_id.required' => 'Vui lòng chọn học kỳ.',
            'semester_id.exists' => 'Học kỳ không tồn tại.',
            'amount.required' => 'Vui lòng nhập số tiền.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
            'due_date.required' => 'Vui lòng nhập ngày hết hạn.',
            'due_date.after_or_equal' => 'Ngày hết hạn không được là ngày trong quá khứ.',
            'note.max' => 'Ghi chú không được quá 255 ký tự.',
            'csv_file.required' => 'Vui lòng tải lên file CSV danh sách mã sinh viên.',
            'csv_file.mimes' => 'File phải là định dạng CSV.',
            'csv_file.max' => 'File không được vượt quá 1MB.',
        ];
    }
}
