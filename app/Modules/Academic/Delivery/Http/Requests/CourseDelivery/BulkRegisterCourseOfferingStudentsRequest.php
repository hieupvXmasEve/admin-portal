<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\CourseDelivery;

use Illuminate\Foundation\Http\FormRequest;

final class BulkRegisterCourseOfferingStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['student_ids' => ['required', 'array'], 'student_ids.*' => ['required', 'string']];
    }
}
