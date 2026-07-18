<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\CourseDelivery;

use App\Models\CourseOffering;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create_course');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(CourseOfferingCatalogReader $catalog): array
    {
        $rules = CourseOffering::validationRules();
        $messages = CourseOffering::validationMessages();
        $rules['semester_id'] = ['required', $this->academicPeriodRule($catalog, $messages['semester_id.exists'])];
        $rules['unit_id'] = ['required', $this->unitRule($catalog, $messages['unit_id.exists'])];
        $rules['syllabus_template_id'] = ['required', $this->syllabusTemplateRule($catalog, $messages['syllabus_template_id.exists'])];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CourseOffering::validationMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'current_enrollment' => 0,
            'current_waitlist' => 0,
            'is_active' => true,
            'enrollment_status' => 'open',
            'grading_type' => $this->input('grading_type', 'grade'),
        ]);
    }

    private function academicPeriodRule(CourseOfferingCatalogReader $catalog, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($catalog, $message): void {
            $academicPeriodId = $this->integerId($value);

            if ($academicPeriodId === null || ! $catalog->hasAcademicPeriod($academicPeriodId)) {
                $fail($message);
            }
        };
    }

    private function unitRule(CourseOfferingCatalogReader $catalog, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($catalog, $message): void {
            $unitId = $this->integerId($value);

            if ($unitId === null || ! $catalog->hasUnit($unitId)) {
                $fail($message);
            }
        };
    }

    private function syllabusTemplateRule(CourseOfferingCatalogReader $catalog, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($catalog, $message): void {
            $syllabusTemplateId = $this->integerId($value);
            $unitId = $this->integerId($this->input('unit_id'));

            if ($syllabusTemplateId === null || ! $catalog->hasSyllabusTemplate($syllabusTemplateId, $unitId)) {
                $fail($message);

                return;
            }

            if (! $catalog->isSyllabusTemplateAssignable($syllabusTemplateId)) {
                $fail('Canvas-linked syllabus templates cannot be reused for another course offering.');
            }
        };
    }

    private function integerId(mixed $value): ?int
    {
        if (! is_int($value) && (! is_string($value) || ! ctype_digit($value))) {
            return null;
        }

        return (int) $value;
    }
}
