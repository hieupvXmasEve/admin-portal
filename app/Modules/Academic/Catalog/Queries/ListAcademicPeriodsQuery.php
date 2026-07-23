<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Semester;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAcademicPeriodsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Semester>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $filter = $filters['filter'] ?? [];

        return Semester::query()
            ->orderByDesc('start_date')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereRaw('YEAR(start_date) = ?', [$search])
                        ->orWhereRaw('YEAR(end_date) = ?', [$search]);
                });
            })
            ->when($filter['name'] ?? null, fn ($query, string $name) => $query->where('name', 'like', "%{$name}%"))
            ->when($filter['year'] ?? null, function ($query, string $year): void {
                $query->where(function ($query) use ($year): void {
                    $query->whereRaw('YEAR(start_date) = ?', [$year])
                        ->orWhereRaw('YEAR(end_date) = ?', [$year]);
                });
            })
            ->when(array_key_exists('is_active', $filter) && $filter['is_active'] !== null, fn ($query) => $query->where('is_active', $filter['is_active']))
            ->when(array_key_exists('is_archived', $filter) && $filter['is_archived'] !== null, fn ($query) => $query->where('is_archived', $filter['is_archived']))
            ->paginate((int) ($filters['per_page'] ?? 10), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }
}
