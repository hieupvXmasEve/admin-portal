<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\CourseDelivery;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeCourseOfferingRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer'],
        ];
    }
}
