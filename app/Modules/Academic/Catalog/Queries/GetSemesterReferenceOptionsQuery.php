<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Semester;
use Illuminate\Support\Collection;

/**
 * Unfiltered semester reference lists for Academic Progression reporting
 * surfaces, so those consumers reach Semester through a Catalog-owned seam
 * instead of importing the shared model directly.
 *
 * Deliberately separate from {@see GetSemesterFilterOptionsQuery}: that seam
 * hides archived semesters, which is correct for a forward-looking picker but
 * wrong for audit and lifecycle reports, where historical rows must stay
 * selectable.
 */
class GetSemesterReferenceOptionsQuery
{
    /**
     * Newest-first `id`/`name`/`code` rows for filter dropdowns.
     *
     * @return Collection<int, Semester>
     */
    public function options(): Collection
    {
        return Semester::query()
            ->select('id', 'name', 'code')
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Newest-first rows carrying the extra fields the lifecycle report renders.
     *
     * @return Collection<int, Semester>
     */
    public function lifecycleOptions(): Collection
    {
        return Semester::query()
            ->select('id', 'name', 'code', 'start_date', 'is_active')
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Newest-first rows carrying every semester column, for the reporting
     * pages whose pickers render fields beyond the reference triple.
     *
     * @return Collection<int, Semester>
     */
    public function records(): Collection
    {
        return Semester::query()
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Newest-first rows with the date range the progression-audit filter shows.
     *
     * @return Collection<int, Semester>
     */
    public function auditOptions(): Collection
    {
        return Semester::query()
            ->select('id', 'name', 'code', 'start_date', 'end_date')
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Newest-first semester codes, skipping semesters that have none.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return Semester::query()
            ->whereNotNull('code')
            ->orderBy('start_date', 'desc')
            ->pluck('code')
            ->values()
            ->all();
    }

    /**
     * Newest semester id, used as a report default when none is active.
     */
    public function latestId(): ?int
    {
        $id = Semester::query()->orderByDesc('start_date')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Code of a single semester, failing the request when it does not exist.
     */
    public function codeOrFail(int $semesterId): string
    {
        return (string) Semester::query()
            ->select('id', 'code')
            ->findOrFail($semesterId)
            ->code;
    }
}
