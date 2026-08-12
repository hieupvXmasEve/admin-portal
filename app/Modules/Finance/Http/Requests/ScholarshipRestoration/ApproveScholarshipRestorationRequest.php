<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\ScholarshipRestoration;

use Illuminate\Foundation\Http\FormRequest;

class ApproveScholarshipRestorationRequest extends FormRequest
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
        return [];
    }
}
