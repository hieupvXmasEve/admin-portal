<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Canvas;

use App\Shared\Contracts\Academic\AcademicPeriodReader;
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

                if (! ctype_digit((string) $value) || app(AcademicPeriodReader::class)->find((int) $value) === null) {
                    $fail("The selected {$attribute} is invalid.");
                }
            }],
        ];
    }
}
