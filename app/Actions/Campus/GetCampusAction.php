<?php

declare(strict_types=1);

namespace App\Actions\Campus;

use App\Models\Campus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class GetCampusAction
{
    /**
     * @return array{0: Campus, 1: LengthAwarePaginator}
     */
    public function execute(Campus $campus, array $filters): array
    {
        $page = request()->integer('page', 1);
        $perPage = $filters['per_page'] ?? 15;
        $search = $filters['search'] ?? null;
        $sort = $filters['sort'] ?? null;
        $direction = $filters['direction'] ?? 'asc';

        $cacheKey = 'campuses:show:' . md5(json_encode([
            'campus_id' => $campus->id,
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]));

        return Cache::tags(['campuses', 'campuses.show'])->rememberForever($cacheKey, function () use ($campus, $search, $sort, $direction, $perPage, $page) {
            // Load campus with counts
            $camp = $campus->fresh()->loadCount(['buildings', 'users']);

            // Get paginated buildings for this campus
            $buildings = $camp->buildings()
                ->when($search, function ($query, $term) {
                    $query->where(function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%")
                            ->orWhere('description', 'like', "%{$term}%")
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

            return [$camp, $buildings];
        });
    }
}
