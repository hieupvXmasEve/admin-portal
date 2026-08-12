<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\ScholarshipRestoration;

use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use Illuminate\Foundation\Http\FormRequest;

class ProposeScholarshipRestorationRequest extends FormRequest
{
    /** Permission gated at the route (can:restore_scholarship) — action re-verifies at the adjustment's campus. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $floor = $this->floor();

        return [
            'reason' => ['required', 'string', 'max:1000'],
            'restored_amount' => [
                'nullable', 'numeric',
                'gt:'.$floor,
                'lt:'.(float) $this->adjustment()->original_amount,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $floor = $this->floor();
        $original = (float) $this->adjustment()->original_amount;

        return [
            'reason.required' => 'Hãy nêu lý do đề xuất khôi phục học bổng.',
            'reason.max' => 'Lý do không được dài quá 1000 ký tự.',
            'restored_amount.numeric' => 'Mức khôi phục phải là một số.',
            'restored_amount.gt' => "Mức khôi phục phải LỚN HƠN mức hiện tại ({$floor}).",
            'restored_amount.lt' => "Mức khôi phục phải NHỎ HƠN mức học bổng gốc ({$original}). Bỏ trống trường này để khôi phục toàn bộ.",
        ];
    }

    /**
     * Lower bound for a partial restoration: the latest approved proposal's
     * restored_amount, or the adjustment's current adjusted_amount if none.
     * Reuses the model's own tie-break so this can never disagree with the
     * floor CreateRestorationProposalAction enforces server-side.
     */
    private function floor(): float
    {
        $adjustment = $this->adjustment();
        $latestApproved = $adjustment->latestApprovedRestoration();

        if ($latestApproved !== null && $latestApproved->restored_amount !== null) {
            return (float) $latestApproved->restored_amount;
        }

        return (float) $adjustment->adjusted_amount;
    }

    private function adjustment(): ScholarshipSemesterAdjustment
    {
        $adjustment = $this->route('adjustment');

        return $adjustment instanceof ScholarshipSemesterAdjustment
            ? $adjustment
            : ScholarshipSemesterAdjustment::query()->findOrFail((int) $adjustment);
    }
}
