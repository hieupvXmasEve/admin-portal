<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\CourseDelivery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorization is enforced by the `can:create_course_offering` route
 * middleware alone, matching RecalculateCourseOfferingRequest's pattern.
 */
final class DuplicateCourseOfferingRequest extends FormRequest
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
            'section_code' => ['required', 'string', 'max:30'],
        ];
    }
}
