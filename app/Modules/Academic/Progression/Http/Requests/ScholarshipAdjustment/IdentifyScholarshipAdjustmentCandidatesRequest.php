<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment;

use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\FormRequest;

class IdentifyScholarshipAdjustmentCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // `can:` middleware on the route only proves the permission "somewhere"
        // (session campus). campus_id is a request field the operator picks —
        // re-verify the permission AT that specific campus, not the session's.
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
            'source_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'target_semester_id' => ['required', 'integer', 'exists:semesters,id'],
        ];
    }
}
