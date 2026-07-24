<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

final class LecturerAttendanceAlertsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['priority' => ['nullable', 'string'], 'type' => ['nullable', 'string'], 'course_offering_id' => ['nullable', 'integer']];
    }
}
