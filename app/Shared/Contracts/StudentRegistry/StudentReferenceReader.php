<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;

interface StudentReferenceReader
{
    public function find(int $studentId): ?StudentReference;

    public function findByStudentCode(string $studentCode, int $campusId): ?StudentReference;

    /**
     * @return list<StudentReference>
     */
    public function search(string $query, int $campusId): array;
}
