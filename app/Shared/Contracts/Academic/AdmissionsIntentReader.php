<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface AdmissionsIntentReader
{
    /** @param array{campus_code?: ?string, intended_program?: ?string, intake?: ?string, intended_specialization?: ?string} $intent
     * @return array{campus_id: ?int, program_id: ?int, intake_semester_id: ?int, curriculum_version_id: ?int, curriculum_match_count: int, specialization_id: ?int}
     */
    public function resolveAdmissionsIntent(array $intent): array;

    /** @return array{campuses: list<array{id: int, code: string, name: string}>, programs: list<array{id: int, code: string, name: string}>, semesters: list<array{id: int, code: string, name: string}>} */
    public function admissionsFormOptions(): array;
}
