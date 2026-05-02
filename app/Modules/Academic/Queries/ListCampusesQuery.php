<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\Campus;
use Illuminate\Pagination\LengthAwarePaginator;

class ListCampusesQuery
{
    /**
     * Return a paginated, filtered, sorted list of campuses.
     *
     * @param array{
     *   search?: string|null,
     *   sort?: string|null,
     *   direction?: string|null,
     *   per_page?: int|null,
     * } $filters
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return Campus::query()
            ->withCount(['buildings', 'users'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('dng_code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($filters['sort'] ?? null, function ($query, $sort) use ($filters) {
                $query->orderBy($sort, $filters['direction'] ?? 'asc');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }
}
