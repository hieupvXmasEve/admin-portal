<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Student360ShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level `can:view_finance_student_overview` gate handles authorization.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'focus' => ['nullable', 'string', 'regex:/^(dng|invoice|charge|payment|installment):\d+$/'],
        ];
    }

    /**
     * Parse the optional focus deep-link target.
     *
     * @return array{type:string,id:int}|null
     */
    public function focusTarget(): ?array
    {
        $focus = (string) $this->query('focus', '');
        if (preg_match('/^(dng|invoice|charge|payment|installment):(\d+)$/', $focus, $m) !== 1) {
            return null;
        }

        return ['type' => $m[1], 'id' => (int) $m[2]];
    }
}
