<?php

declare(strict_types=1);

namespace App\Actions\Campus;

use App\Models\Campus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCampusAction
{
    /**
     * Execute the action.
     *
     * @param array{
     *     search?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|null
     * } $filters
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $page = request()->integer('page', 1);
        $perPage = $filters['per_page'] ?? 15;
        $search = $filters['search'] ?? null;
        $sort = $filters['sort'] ?? null;
        $direction = $filters['direction'] ?? 'asc';

        return Campus::query()
            ->withCount(['buildings', 'users'])
            ->when($search, function ($query, $term) {
                $query->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%")
                            ->orWhere('dng_code', 'like', "%{$term}%")
                            ->orWhere('address', 'like', "%{$term}%");
                    });
                })
                ->when($sort, function ($query, $column) use ($direction) {
                    $query->orderBy($column, $direction);
                })
                ->unless($sort, function ($query) {
                    $query->orderBy('created_at', 'desc');
                })
                ->paginate($perPage, ['*'], 'page', $page);
    }
}
