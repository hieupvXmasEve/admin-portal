<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmScholarshipAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level `student.api.auth` already guarantees a Student actor;
        // per-record ownership is enforced in the controller from the
        // authenticated student id.
        return true;
    }

    public function rules(): array
    {
        return [
            'minutes_version' => ['required', 'integer', 'min:0'],
            'agree' => ['required', 'boolean'],
            // A dispute should carry a reason; an agreement may add an optional note.
            'comment' => ['nullable', 'string', 'max:2000', 'required_if:agree,false'],
        ];
    }
}
