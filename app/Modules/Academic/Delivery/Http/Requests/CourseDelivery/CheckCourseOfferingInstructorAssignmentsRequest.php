<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\CourseDelivery;

use Illuminate\Foundation\Http\FormRequest;

final class CheckCourseOfferingInstructorAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['semester_id' => ['required', 'integer']];
    }
}
