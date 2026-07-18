<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCampusDetailQuery
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|null}  $filters
     * @return array{campus: Campus, buildings: LengthAwarePaginator}
     */
    public function handle(int|string $campusId, array $filters): array
    {
        $campus = Campus::query()->findOrFail($campusId);
        $campus->loadCount(['buildings', 'users']);

        $buildings = $campus->buildings()
            ->when($filters['search'] ?? null, function (HasMany $query, string $search): void {
                $query->where(function (Builder $buildingQuery) use ($search): void {
                    $buildingQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($filters['sort'] ?? null, fn (HasMany $query, string $sort): HasMany => $query->orderBy($sort, $filters['direction'] ?? 'asc'))
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ['campus' => $campus, 'buildings' => $buildings];
    }
}
