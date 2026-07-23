<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Unit;
use Illuminate\Support\Collection;

class SearchUnitsQuery
{
    /**
     * @param  array{q: string, exclude?: string|null, limit?: int|null}  $filters
     * @return Collection<int, Unit>
     */
    public function handle(array $filters): Collection
    {
        $excludedIds = array_values(array_filter(
            array_map('intval', explode(',', $filters['exclude'] ?? '')),
            fn (int $id): bool => $id > 0,
        ));

        return Unit::query()
            ->where(function ($query) use ($filters): void {
                $query->where('code', 'like', "%{$filters['q']}%")
                    ->orWhere('name', 'like', "%{$filters['q']}%");
            })
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->limit($filters['limit'] ?? 10)
            ->get(['id', 'code', 'name', 'credit_points']);
    }
}
