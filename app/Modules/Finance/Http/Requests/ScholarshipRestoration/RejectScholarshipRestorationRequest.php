<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\ScholarshipRestoration;

use Illuminate\Foundation\Http\FormRequest;

class RejectScholarshipRestorationRequest extends FormRequest
{
    /** Permission gated at the route (can:approve_scholarship_adjustment) — action re-verifies at the proposal's campus. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Hãy nêu lý do từ chối đề xuất khôi phục.',
            'reason.max' => 'Lý do không được dài quá 1000 ký tự.',
        ];
    }
}
