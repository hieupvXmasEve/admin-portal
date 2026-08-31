<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\RetakeCourse;

use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CancelRetakeCourseRequest extends FormRequest
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
            'acknowledge_no_refund' => ['nullable', 'boolean'],
            'fee_outcome' => ['nullable', 'string', 'in:forfeit,keep_for_later'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $registration = $this->route('registration');
            if (! $registration instanceof CourseRetakeRegistration) {
                return;
            }

            $settlement = app(AcademicObligationSettlement::class);
            $isPaid = $registration->hq_fee_status === CourseRetakeRegistration::HQ_FEE_PAID
                || $settlement->hasRetakePaidEvidence($registration);

            if ($isPaid && ! $this->boolean('acknowledge_no_refund')) {
                $validator->errors()->add(
                    'acknowledge_no_refund',
                    'Vui lòng xác nhận hủy đăng ký học lại và hệ quả phí đã thanh toán (không hoàn phí).'
                );
            }

            if ($isPaid && ! in_array($this->input('fee_outcome'), ['forfeit', 'keep_for_later'], true)) {
                $validator->errors()->add(
                    'fee_outcome',
                    'Vui lòng chọn mất phí hoặc lưu phí dùng sau khi hủy đăng ký học lại đã thanh toán.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Lý do hủy là bắt buộc.',
            'reason.min' => 'Lý do hủy phải có ít nhất 5 ký tự.',
            'acknowledge_no_refund.boolean' => 'Xác nhận không hoàn phí không hợp lệ.',
            'fee_outcome.in' => 'Hệ quả phí không hợp lệ.',
        ];
    }
}
