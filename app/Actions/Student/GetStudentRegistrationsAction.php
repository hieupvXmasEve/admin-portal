<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;
use App\Services\StudentAcademicSummaryService;
use Illuminate\Pagination\LengthAwarePaginator;

class GetStudentRegistrationsAction
{
    public function __construct(
        protected StudentAcademicSummaryService $service
    ) {}

    /**
     * Get paginated registrations for a student with filters.
     *
     * @param Student $student
     * @param array $filters
     * @return array
     */
    public function execute(Student $student, array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? 50);
        
        return $this->service->getRegistrationsData($student, $filters, $perPage);
    }
}
