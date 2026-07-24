<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Delivery;

use App\Models\CourseOffering;
use Illuminate\Foundation\Http\FormRequest;

final class CourseOfferingCampusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $courseOffering = $this->route('courseOffering');

        return $courseOffering instanceof CourseOffering && $courseOffering->campus_id === app('campus')->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }
}
