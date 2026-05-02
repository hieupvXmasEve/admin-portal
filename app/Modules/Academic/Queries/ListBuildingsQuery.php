<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\Building;
use App\Models\Campus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ListBuildingsQuery
{
    /**
     * Return a paginated, filtered, sorted list of buildings.
     *
     * @param array{
     *   search?: string|null,
     *   campus_id?: int|null,
     *   sort?: string|null,
     *   direction?: string|null,
     *   per_page?: int|null,
     * } $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return Building::query()
            ->with(['campus'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('campus', function ($campusQuery) use ($search) {
                            $campusQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['campus_id'] ?? null, function ($query, $campusId) {
                $query->where('campus_id', $campusId);
            })
            ->when($filters['sort'] ?? null, function ($query, $sort) use ($filters) {
                $direction = $filters['direction'] ?? 'asc';
                if ($sort === 'campus_id') {
                    $query->join('campuses', 'buildings.campus_id', '=', 'campuses.id')
                        ->orderBy('campuses.name', $direction)
                        ->select('buildings.*');
                } else {
                    $query->orderBy($sort, $direction);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Return all campuses for use as filter options.
     */
    public function getCampusOptions(): Collection
    {
        return Campus::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Return a lightweight buildings list for dropdown/select usage.
     *
     * @param  int|null  $campusId  filter by campus
     * @param  string|null  $search  optional search term
     */
    public function getForDropdown(?int $campusId = null, ?string $search = null): Collection
    {
        return Building::select('id', 'name', 'code', 'campus_id')
            ->with(['campus:id,name'])
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(50)
            ->get();
    }
}
