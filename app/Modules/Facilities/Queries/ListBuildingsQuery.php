<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Queries;

use App\Models\Building;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ListBuildingsQuery
{
    public function __construct(private readonly CampusReferenceReader $campusReferences) {}

    /**
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
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('campus', fn ($campusQuery) => $campusQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['campus_id'] ?? null, fn ($query, $campusId) => $query->where('campus_id', $campusId))
            ->when($filters['sort'] ?? null, function ($query, $sort) use ($filters) {
                $direction = $filters['direction'] ?? 'asc';
                if ($sort === 'campus_id') {
                    $query->join('campuses', 'buildings.campus_id', '=', 'campuses.id')
                        ->orderBy('campuses.name', $direction)
                        ->select('buildings.*');

                    return;
                }

                $query->orderBy($sort, $direction);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /** @return Collection<int, array{id: int, name: string}> */
    public function getCampusOptions(): Collection
    {
        return collect($this->campusReferences->all())
            ->map(fn ($campus) => ['id' => $campus->id, 'name' => $campus->name])
            ->sortBy('name')
            ->values();
    }

    /** @return Collection<int, Building> */
    public function getForDropdown(?int $campusId = null, ?string $search = null): Collection
    {
        return Building::select('id', 'name', 'code', 'campus_id')
            ->with(['campus:id,name'])
            ->when($campusId, fn ($query) => $query->where('campus_id', $campusId))
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(50)
            ->get();
    }
}
