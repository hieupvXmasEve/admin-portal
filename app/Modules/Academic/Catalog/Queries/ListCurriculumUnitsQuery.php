<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\CurriculumUnit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListCurriculumUnitsQuery
{
    /** @param array<string, mixed> $filters @return LengthAwarePaginator<int, CurriculumUnit> */
    public function handle(array $filters): LengthAwarePaginator
    {
        return CurriculumUnit::query()
            ->with(['curriculumVersion.program', 'curriculumVersion.specialization', 'unit', 'semester'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('unit', fn ($unitQuery) => $unitQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($filters['filter']['curriculum_version_id'] ?? null, fn ($query, int $id) => $query->where('curriculum_version_id', $id))
            ->when($filters['filter']['unit_scope'] ?? null, fn ($query, string $scope) => $query->where('unit_scope', $scope))
            ->when($filters['sort'] ?? null, fn ($query, string $sort) => $query->orderBy($sort, $filters['direction'] ?? 'asc'))
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }
}
