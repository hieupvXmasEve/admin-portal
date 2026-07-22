<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Program;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProgramsQuery
{
    /**
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, Program>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return Program::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($filters['sort'] ?? null, function ($query, string $sort) use ($filters): void {
                $query->orderBy($sort, $filters['direction'] ?? 'asc');
            })
            ->orderByDesc('created_at')
            ->withCount(['specializations', 'curriculumVersions'])
            ->paginate((int) ($filters['per_page'] ?? 15));
    }
}
