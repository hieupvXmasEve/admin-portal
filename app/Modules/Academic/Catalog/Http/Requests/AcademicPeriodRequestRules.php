<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use App\Models\Semester;
use Illuminate\Validation\Rule;

final class AcademicPeriodRequestRules
{
    /** @return array<string, mixed> */
    public static function store(): array
    {
        return self::rules(null);
    }

    /** @return array<string, mixed> */
    public static function update(Semester $academicPeriod): array
    {
        return self::rules($academicPeriod);
    }

    /** @return array<string, mixed> */
    private static function rules(?Semester $academicPeriod): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('semesters', 'code')->ignore($academicPeriod?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'enrollment_start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'enrollment_end_date' => ['nullable', 'date', 'after_or_equal:enrollment_start_date', 'before_or_equal:end_date'],
            'is_active' => ['boolean'],
            'is_archived' => ['boolean'],
        ];
    }
}
