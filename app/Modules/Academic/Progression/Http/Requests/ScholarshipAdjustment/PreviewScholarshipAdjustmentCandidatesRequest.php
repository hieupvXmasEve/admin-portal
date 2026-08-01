<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment;

use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\FormRequest;

class PreviewScholarshipAdjustmentCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Read-only, but exposes per-student failed-course data — gate on the
        // same permission as actually creating candidates, and re-verify AT
        // the campus_id the operator picked, not the session campus.
        $campusId = $this->integer('campus_id');

        if ($campusId <= 0) {
            return false;
        }

        $codes = app(CampusPermissionReader::class)->permissionCodesForUserId((int) $this->user()->id, $campusId);

        return in_array('manage_scholarship_adjustment_candidate', $codes, true);
    }

    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'source_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'target_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ];
    }
}
