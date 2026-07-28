<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

/**
 * Catalog reference lists rendered by the student directory pages.
 *
 * Academic Catalog owns Program, Specialization, CurriculumVersion, and
 * Semester, so the directory reads them through this contract. Every method
 * returns plain arrays in the exact shape the existing Inertia props carried.
 */
interface StudentDirectoryFormOptionsReader
{
    /**
     * Dropdown sources for the directory listing's filter bar.
     *
     * @return array{
     *     programs: list<array<string, mixed>>,
     *     specializations: list<array<string, mixed>>,
     *     intake_semesters: list<array<string, mixed>>
     * }
     */
    public function filterOptions(): array;

    /**
     * Program, specialization, and curriculum-version sources for the create form.
     *
     * @return array{
     *     programs: list<array<string, mixed>>,
     *     specializations: list<array<string, mixed>>,
     *     curriculumVersions: list<array<string, mixed>>
     * }
     */
    public function createOptions(): array;

    /**
     * Program list plus the curriculum versions available to one student's
     * program, narrowed by specialization when the student has one.
     *
     * @return array{
     *     programs: list<array<string, mixed>>,
     *     curriculumVersions: list<array<string, mixed>>
     * }
     */
    public function editOptions(?int $programId, ?int $specializationId): array;
}
