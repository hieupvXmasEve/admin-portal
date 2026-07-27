<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListCourseRegistrationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'semester_id' => ['nullable', $this->identifierOrAllRule('semesters')],
            'status' => ['nullable', 'string', Rule::in(['all', 'registered', 'confirmed', 'dropped', 'withdrawn', 'completed', 'defer'])],
            'course_offering_id' => ['nullable', $this->identifierOrAllRule('course_offerings')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function identifierOrAllRule(string $table): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($table): void {
            if ($value === null || $value === '' || $value === 'all') {
                return;
            }

            if ((! is_int($value) && (! is_string($value) || ! ctype_digit($value))) || ! \DB::table($table)->where('id', (int) $value)->exists()) {
                $fail("The selected {$attribute} is invalid.");
            }
        };
    }
}
