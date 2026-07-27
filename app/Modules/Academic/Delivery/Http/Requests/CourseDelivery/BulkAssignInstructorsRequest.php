<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\CourseDelivery;

use Illuminate\Foundation\Http\FormRequest;

final class BulkAssignInstructorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array'],
            'assignments.*.course_offering_id' => ['required', 'integer', 'exists:course_offerings,id'],
            'assignments.*.lecture_id' => ['required', 'integer'],
        ];
    }
}
