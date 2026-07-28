<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\ExamResit;

use App\Models\ExamResitAttempt;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'acknowledge_no_refund' => ['nullable', 'boolean'],
            'confirmation' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $attempt = $this->route('examResit');
            if (! $attempt instanceof ExamResitAttempt) {
                return;
            }

            $settlement = app(AcademicObligationSettlement::class);
            $isPaid = $attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_PAID
                || $settlement->hasExamResitPaidEvidence($attempt);

            if ($isPaid && ! $this->boolean('acknowledge_no_refund')) {
                $validator->errors()->add(
                    'acknowledge_no_refund',
                    'Vui lòng xác nhận hủy thi lại nhưng giữ nguyên khoản phí đã thanh toán và không tạo hoàn phí.'
                );
            }

            $hasUnpaidCharge = $attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_CHARGE_CREATED
                && ! $isPaid
                && $settlement->hasUnsettledExamResitObligation($attempt);

            if (! $isPaid
                && $hasUnpaidCharge
                && $this->input('confirmation') !== ExamResitAttempt::CONFIRM_VOID_UNPAID_EXAM_RESIT_FEE) {
                $validator->errors()->add(
                    'confirmation',
                    'Vui lòng xác nhận hủy khoản phí/DNG đang chờ thu trước khi hủy thi lại.'
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
            'confirmation.string' => 'Xác nhận hủy phí không hợp lệ.',
        ];
    }
}
