<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUnitsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Unit>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return Unit::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('unit_type', $type))
            ->when(isset($filters['level']), fn ($query) => $query->where('level', $filters['level']))
            ->when(
                $filters['sort'] ?? null,
                fn ($query, string $sort) => $query->orderBy($sort, $filters['direction'] ?? 'asc'),
                fn ($query) => $query->orderByDesc('created_at'),
            )
            ->withCount(['equivalentUnits', 'curriculumUnits', 'prerequisiteConditions', 'syllabusTemplates'])
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }
}
