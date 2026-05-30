<?php

declare(strict_types=1);

namespace App\Rules\CourseOffering;

use App\Models\CourseOffering;
use App\Models\SyllabusTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AssignableSyllabusTemplate implements ValidationRule
{
    public function __construct(
        private readonly ?CourseOffering $courseOffering = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isAssignable = SyllabusTemplate::query()
            ->assignableToCourseOffering($this->courseOffering)
            ->whereKey($value)
            ->exists();

        if (! $isAssignable) {
            $fail('Canvas-linked syllabus templates cannot be reused for another course offering.');
        }
    }
}
