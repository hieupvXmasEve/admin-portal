<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment;

use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use Illuminate\Foundation\Http\FormRequest;

class DecideScholarshipAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide_scholarship_adjustment')
            && $this->user()->can('decide', $this->route('dossier'));
    }

    public function rules(): array
    {
        return [
            'decision_type' => ['required', 'string', 'in:keep,reduce,suspend_full,defer,cancel'],
            'adjusted_amount' => ['nullable', 'numeric', 'min:0', ...$this->adjustedAmountCeiling()],
            'reason' => ['required', 'string', 'max:1000'],
            'exception_override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Spelled out because the app ships no lang/ directory: an unmapped rule
     * renders as the raw key ("validation.required") instead of a sentence.
     */
    public function messages(): array
    {
        return [
            'decision_type.required' => 'Hãy chọn hình thức xử lý học bổng.',
            'decision_type.in' => 'Hình thức xử lý này không hợp lệ.',
            'reason.required' => 'Hãy nêu lý do chọn hình thức xử lý này — nội dung được lưu vào hồ sơ.',
            'reason.max' => 'Lý do không được dài quá 1000 ký tự.',
            'adjusted_amount.numeric' => 'Mức học bổng còn lại phải là một số.',
            'adjusted_amount.min' => 'Mức học bổng còn lại không được là số âm.',
            'adjusted_amount.max' => 'Mức học bổng còn lại không được cao hơn mức đã cấp ('
                .$this->originalAwardLabel().'). Hãy nhập phần CÒN LẠI sau khi giảm, không phải phần bị trừ đi.',
            'exception_override_reason.max' => 'Lý do bỏ qua điều kiện không được dài quá 1000 ký tự.',
        ];
    }

    /**
     * An adjustment may only ever reduce the award. Without this ceiling a
     * value above the original passes validation, gets silently clamped by the
     * discount resolver, and the decision records as applied while changing
     * nothing — a fee decision that looks done but is a no-op.
     *
     * @return array<int, string>
     */
    private function adjustedAmountCeiling(): array
    {
        $original = $this->dossier()?->original_amount;

        return $original === null ? [] : ['max:'.$original];
    }

    private function originalAwardLabel(): string
    {
        $dossier = $this->dossier();
        $amount = $dossier?->original_amount;

        if ($amount === null) {
            return 'chưa xác định';
        }

        return $dossier?->original_type === 'percentage'
            ? $amount.'%'
            : number_format((float) $amount).' VND';
    }

    private function dossier(): ?ScholarshipAdjustmentDossier
    {
        $dossier = $this->route('dossier');

        return $dossier instanceof ScholarshipAdjustmentDossier ? $dossier : null;
    }
}
