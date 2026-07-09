<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Charges;

use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Structural validation for the split-installments admin form.
 *
 * Domain invariants that depend on the charge state (sum == net_split_target,
 * paid-installment lock, credit charge guard) live in
 * SplitChargeIntoInstallmentsAction so they share the same DB transaction.
 *
 * Authorization: delegated to FinanceChargePolicy::splitInstallment via the
 * controller's $this->authorize('splitInstallment', $charge) call.
 */
class SplitChargeIntoInstallmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy enforced at controller level
    }

    public function rules(): array
    {
        return [
            'installments' => ['required', 'array', 'min:1', 'max:24'],
            'installments.*.installment_no' => ['required', 'integer', 'min:1'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0'],
            'installments.*.due_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'installments.required' => 'Kế hoạch đợt không được rỗng.',
            'installments.min' => 'Phải có ít nhất 1 đợt.',
            'installments.max' => 'Tối đa 24 đợt cho 1 khoản phí.',
            'installments.*.installment_no.required' => 'Mỗi đợt phải có số thứ tự.',
            'installments.*.amount.gt' => 'Số tiền mỗi đợt phải lớn hơn 0.',
            'installments.*.due_date.required' => 'Mỗi đợt phải có hạn thanh toán.',
        ];
    }

    /**
     * Resolve the route-model bound charge, kept here so the controller and
     * action share the same accessor without re-fetching.
     */
    public function charge(): FinanceCharge
    {
        return $this->route('charge');
    }
}
