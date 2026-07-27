<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use App\Models\Campus;
use Illuminate\Foundation\Http\FormRequest;

class LectureStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_lecturer') ?? false;
    }

    public function rules(): array
    {
        return [
            'campus_id' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '' || $value === 'all') {
                    return;
                }

                if (! ctype_digit((string) $value) || ! Campus::query()->whereKey((int) $value)->exists()) {
                    $fail("The selected {$attribute} is invalid.");
                }
            }],
        ];
    }
}
