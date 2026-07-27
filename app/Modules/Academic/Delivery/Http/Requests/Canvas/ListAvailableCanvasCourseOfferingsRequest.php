<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Canvas;

use App\Models\Semester;
use Illuminate\Foundation\Http\FormRequest;

class ListAvailableCanvasCourseOfferingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'semester_id' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '' || $value === 'all') {
                    return;
                }

                if (! ctype_digit((string) $value) || ! Semester::query()->whereKey((int) $value)->exists()) {
                    $fail("The selected {$attribute} is invalid.");
                }
            }],
        ];
    }
}
