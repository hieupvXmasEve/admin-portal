<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Support\SemesterContextResolver;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SetSelectedAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(AcademicPeriodReader $academicPeriods): array
    {
        return [
            'semester_id' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) use ($academicPeriods): void {
                    if ($value === null || $value === '' || $value === SemesterContextResolver::AllSemesters) {
                        return;
                    }

                    if ((! is_int($value) && (! is_string($value) || ! ctype_digit($value)))
                        || $academicPeriods->find((int) $value) === null) {
                        $fail('The selected academic period is invalid.');
                    }
                },
            ],
        ];
    }

    public function academicPeriodId(): ?int
    {
        $value = $this->input('semester_id');

        if ($value === null || $value === '' || $value === SemesterContextResolver::AllSemesters) {
            return null;
        }

        return (int) $value;
    }
}
