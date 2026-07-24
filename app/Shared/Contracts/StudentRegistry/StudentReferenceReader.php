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
     * Resolve a student code without applying a campus scope.
     *
     * Callers that act on a campus must still compare the returned reference's
     * campus ID with their current campus context.
     */
    public function findByStudentCodeAnywhere(string $studentCode): ?StudentReference;

    /**
     * @return list<int>
     */
    public function idsForCampus(int $campusId): array;

    /**
     * @return list<int>
     */
    public function activeIdsForCampus(?int $campusId = null): array;

    /**
     * @return list<int>
     */
    public function idsMatchingSearch(string $query, ?int $campusId = null): array;

    /**
     * @return list<StudentReference>
     */
    public function search(string $query, int $campusId, int $limit = 10): array;
}
