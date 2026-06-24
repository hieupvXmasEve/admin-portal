<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface AiAcademicEntitySearchReader
{
    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function searchStudents(string $query, ?int $campusId, array $filters, int $limit): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function searchPrograms(string $query, array $filters, int $limit): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function searchSemesters(string $query, array $filters, int $limit): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function searchCourseOfferings(string $query, ?int $campusId, array $filters, int $limit): array;
}
