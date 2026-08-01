<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\ScholarshipAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class ApproveScholarshipAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve_scholarship_adjustment')
            && $this->user()->can('approve', $this->route('dossier'));
    }

    public function rules(): array
    {
        return [];
    }
}
