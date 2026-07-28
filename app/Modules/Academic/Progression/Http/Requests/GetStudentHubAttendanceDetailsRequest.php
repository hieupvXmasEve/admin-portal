<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetStudentHubAttendanceDetailsRequest extends FormRequest
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
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'nullable|exists:semesters,id',
            'course_offering_id' => 'nullable|exists:course_offerings,id',
        ];
    }
}
