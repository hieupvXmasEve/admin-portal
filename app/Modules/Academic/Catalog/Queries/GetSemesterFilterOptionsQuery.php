<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Semester;
use Illuminate\Support\Collection;

/**
 * Semester dropdown/default options shared by non-Catalog Academic reporting
 * pages, so consumers reach Semester through a Catalog-owned seam instead of
 * importing the shared model directly.
 *
 * Deliberately does not reuse `AcademicPeriodReader::selectable()`: that
 * reader's `active()` adds an `orderByDesc('id')` tiebreak that
 * `Semester::getActiveSemester()` doesn't have, and its DTO shape differs
 * from the raw `id/name/code` rows this page's Inertia props expect.
 */
class GetSemesterFilterOptionsQuery
{
    /**
     * @return array{active_semester_id: int|null, semesters: Collection<int, Semester>}
     */
    public function handle(): array
    {
        $activeSemester = Semester::getActiveSemester();

        $semesters = Semester::where('is_archived', false)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'code']);

        return [
            'active_semester_id' => $activeSemester?->id,
            'semesters' => $semesters,
        ];
    }
}
