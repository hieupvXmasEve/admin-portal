<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\CourseDelivery;

use Illuminate\Foundation\Http\FormRequest;

final class SearchCourseOfferingStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['student_ids' => ['required', 'string']];
    }
}
