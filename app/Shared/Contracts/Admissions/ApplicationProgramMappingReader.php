<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Admissions;

interface ApplicationProgramMappingReader
{
    /**
     * @param  array{campus_code?: ?string, intended_program?: ?string, intake?: ?string, intended_specialization?: ?string}  $applicationData
     * @return array{campus_id: ?int, program_id: ?int, intake_semester_id: ?int, curriculum_version_id: ?int, curriculum_match_count: int, specialization_id: ?int}
     */
    public function resolve(array $applicationData): array;
}
