<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\CourseDelivery;

use Illuminate\Foundation\Http\FormRequest;

final class BulkDeleteCourseOfferingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:course_offerings,id'],
        ];
    }
}
