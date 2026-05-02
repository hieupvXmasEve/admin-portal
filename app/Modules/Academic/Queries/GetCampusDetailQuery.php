<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\Campus;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCampusDetailQuery
{
    /**
     * Load campus detail with counts and paginated buildings.
     *
     * @param array{
     *   search?: string|null,
     *   sort?: string|null,
     *   direction?: string|null,
     *   per_page?: int|null,
     * } $filters  Building filters for the nested table
     * @return array{campus: Campus, buildings: LengthAwarePaginator}
     */
    public function handle(Campus $campus, array $filters): array
    {
        $campus->loadCount(['buildings', 'users']);

        $buildings = $campus->buildings()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($filters['sort'] ?? null, function ($query, $sort) use ($filters) {
                $query->orderBy($sort, $filters['direction'] ?? 'asc');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ['campus' => $campus, 'buildings' => $buildings];
    }
}
