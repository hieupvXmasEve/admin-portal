<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;

interface StudentReferenceReader
{
    public function find(int $studentId): ?StudentReference;

    /**
     * @param  list<int>  $studentIds
     * @return array<int, StudentReference>
     */
    public function findMany(array $studentIds): array;

    public function findByStudentCode(string $studentCode, int $campusId): ?StudentReference;

    /**
     * @return list<int>
     */
    public function idsForCampus(int $campusId): array;

    /**
     * @return list<int>
     */
    public function idsMatchingSearch(string $query, ?int $campusId = null): array;

    /**
     * @return list<StudentReference>
     */
    public function search(string $query, int $campusId): array;
}
