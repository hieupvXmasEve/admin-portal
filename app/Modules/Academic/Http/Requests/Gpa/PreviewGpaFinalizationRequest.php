<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Gpa;

use Illuminate\Foundation\Http\FormRequest;

class PreviewGpaFinalizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campusId = $this->input('campus_id');

        // No campus_id => the controller falls back to the session campus, which
        // is already gated by the campus-switch flow. Only an explicit campus_id
        // needs a membership check, mirroring SelectCampusRequest.
        if ($campusId === null) {
            return true;
        }

        return (bool) $this->user()?->campuses()->where('campuses.id', $campusId)->exists();
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
        ];
    }
}
