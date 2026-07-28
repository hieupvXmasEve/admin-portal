<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachDecisionToTransitionRequest extends FormRequest
{
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
            'source' => ['required', 'in:action,progression'],
            'source_id' => ['required', 'integer'],
            'decision_id' => ['required', 'integer', 'exists:student_decisions,id'],
        ];
    }
}
