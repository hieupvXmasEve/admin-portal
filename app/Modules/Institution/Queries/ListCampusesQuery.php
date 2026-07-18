<?php

declare(strict_types=1);

namespace App\Modules\Institution\Queries;

use App\Models\Campus;
use App\Shared\Contracts\Academic\CampusBuildingCountReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListCampusesQuery
{
    public function __construct(
        private readonly CampusBuildingCountReader $buildingCounts,
    ) {}

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|null}  $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $campuses = Campus::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $campusQuery) use ($search): void {
                    $campusQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($filters['sort'] ?? null, fn (Builder $query, string $sort): Builder => $query->orderBy($sort, $filters['direction'] ?? 'asc'))
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        $buildingCounts = $this->buildingCounts->countByCampusIds(
            $campuses->getCollection()->pluck('id')->map(fn (int $id): int => $id)->all(),
        );
        $campuses->getCollection()->each(function (Campus $campus) use ($buildingCounts): void {
            $campus->setAttribute('buildings_count', $buildingCounts[$campus->id] ?? 0);
        });

        return $campuses;
    }
}
