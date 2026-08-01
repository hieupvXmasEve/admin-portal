<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class PreviewScholarshipAdjustmentImpactRequest extends FormRequest
{
    /**
     * Read-only preview: record-level campus access is enforced by the
     * controller's `view` policy check on the resolved dossier, so nothing
     * further is needed here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Mirrors DecideScholarshipAdjustmentRequest so a value that
            // previews cleanly is a value the decision endpoint also accepts.
            'adjusted_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
