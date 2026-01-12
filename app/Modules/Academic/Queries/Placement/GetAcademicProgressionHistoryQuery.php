<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries\Placement;

use App\Enums\AcademicProgressionEventType;
use App\Models\AcademicProgressionEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAcademicProgressionHistoryQuery
{
    /**
     * Get academic progression history for a student.
     *
     * @param int $studentId
     * @param array $filters {
     *     event_type: ?string,
     *     per_page: ?int,
     * }
     */
    public function handle(int $studentId, array $filters = []): LengthAwarePaginator
    {
        $query = AcademicProgressionEvent::query()
            ->with(['semester', 'createdBy', 'ieltsCertificate'])
            ->where('student_id', $studentId)
            ->latestFirst();

        if (! empty($filters['event_type'])) {
            $query->byEventType($filters['event_type']);
        }

        $perPage = $filters['per_page'] ?? 10;

        return $query->paginate($perPage);
    }
}
