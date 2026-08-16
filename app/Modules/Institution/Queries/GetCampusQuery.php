<?php

declare(strict_types=1);

namespace App\Modules\Institution\Queries;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCampusQuery
{
    /**
     * @return array{id: int, name: string, code: string, address: string}
     */
    public function forEdit(int|string $campusId): array
    {
        $campus = Campus::query()->findOrFail($campusId);

        return [
            'id' => (int) $campus->id,
            'name' => (string) $campus->name,
            'code' => (string) $campus->code,
            'address' => (string) $campus->address,
        ];
    }

    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|null}  $filters
     * @return array{campus: Campus, buildings: LengthAwarePaginator}
     */
    public function forShow(int|string $campusId, array $filters): array
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
