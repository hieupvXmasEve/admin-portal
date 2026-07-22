<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\CourseDelivery;

use Illuminate\Foundation\Http\FormRequest;

final class BulkUpdateCourseRegistrationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'from_status' => ['required', 'in:registered,confirmed,dropped,withdrawn,completed'],
            'to_status' => ['required', 'in:registered,confirmed,dropped,withdrawn,completed'],
            'student_ids' => ['required', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ];
    }
}
