<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

final class CurriculumUnitRequestRules
{
    /** @return array<string, list<string>> */
    public static function webStore(): array
    {
        return array_merge(self::webBase(), [
            'year_level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'semester_number' => ['nullable', 'integer', 'min:1', 'max:9'],
        ]);
    }

    /** @return array<string, list<string>> */
    public static function webUpdate(): array
    {
        return array_merge(self::webBase(), [
            'year_level' => ['required', 'integer', 'min:1', 'max:6'],
            'semester_number' => ['required', 'integer', 'min:1', 'max:9'],
        ]);
    }

    /** @return array<string, list<string>> */
    public static function apiStore(): array
    {
        return [
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'unit_scope' => ['nullable', 'in:program,common,specialization_specific,cross_program'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:6'],
            'semester_number' => ['nullable', 'integer', 'min:1', 'max:9'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function apiUpdate(): array
    {
        return array_merge(self::apiStore(), [
            'unit_scope' => ['required', 'in:program,common,specialization_specific,cross_program'],
        ]);
    }

    /** @return array<string, list<string>> */
    private static function webBase(): array
    {
        return [
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'type' => ['required', 'in:core,major,elective'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
